<?php

namespace IdeallyStudio\MerchantToolkit\Model;

use IdeallyStudio\MerchantToolkit\Model\ProductPreviewParameters;
use IdeallyStudio\MerchantToolkit\Model\ProductPreviewTokenManager;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use const FILTER_VALIDATE_URL;

/**
 * Resolves storefront URLs for products across different store views.
 */
class ProductViewUrlResolver
{
    /**
     * @var UrlFinderInterface
     */
    private $urlFinder;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var ProductResource
     */
    private $productResource;

    /**
     * @var ProductPreviewTokenManager
     */
    private $previewTokenManager;

    /**
     * ProductViewUrlResolver constructor.
     *
     * @param UrlFinderInterface $urlFinder
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     * @param ProductResource $productResource
     * @param ProductPreviewTokenManager $previewTokenManager
     */
    public function __construct(
        UrlFinderInterface $urlFinder,
        StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection,
        ProductResource $productResource,
        ProductPreviewTokenManager $previewTokenManager
    ) {
        $this->urlFinder = $urlFinder;
        $this->storeManager = $storeManager;
        $this->resourceConnection = $resourceConnection;
        $this->productResource = $productResource;
        $this->previewTokenManager = $previewTokenManager;
    }

    /**
     * Retrieve view URLs for the provided product instance.
     *
     * @param ProductInterface $product
     * @return array<int, array<string, mixed>>
     */
    public function getStoreUrls(ProductInterface $product): array
    {
        $productId = (int)$product->getId();
        if (!$productId) {
            return [];
        }

        $storeIds = $this->extractStoreIds($product);

        return $this->buildStoreUrls(
            $productId,
            $storeIds,
            $this->resolveProductStatuses($productId, $storeIds)
        );
    }

    /**
     * Retrieve view URLs for the product identified by its ID.
     *
     * @param int $productId
     * @return array<int, array<string, mixed>>
     */
    public function getStoreUrlsById(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        $storeIds = $this->fetchStoreIds($productId);

        return $this->buildStoreUrls(
            $productId,
            $storeIds,
            $this->resolveProductStatuses($productId, $storeIds)
        );
    }

    /**
     * Extract store IDs attached to the provided product entity.
     *
     * @param ProductInterface $product
     * @return int[]
     */
    private function extractStoreIds(ProductInterface $product): array
    {
        $storeIds = $product->getStoreIds();

        if (!is_array($storeIds) || !$storeIds) {
            $storeIds = $this->fetchStoreIds((int)$product->getId());
        }

        return array_values(array_unique(array_map('intval', $storeIds)));
    }

    /**
     * Retrieve store IDs by traversing the product-to-website relation.
     *
     * @param int $productId
     * @return int[]
     */
    private function fetchStoreIds(int $productId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('catalog_product_website');

        $websiteIds = $connection->fetchCol(
            $connection->select()
                ->from($table, 'website_id')
                ->where('product_id = ?', $productId)
        );

        if (!$websiteIds) {
            return [];
        }

        $storeIds = [];
        foreach ($websiteIds as $websiteId) {
            try {
                $website = $this->storeManager->getWebsite((int)$websiteId);
            } catch (NoSuchEntityException $exception) {
                continue;
            }

            foreach ($website->getStores() as $store) {
                if ($store->isActive()) {
                    $storeIds[] = (int)$store->getId();
                }
            }
        }

        return array_values(array_unique($storeIds));
    }

    /**
     * Build the response structure containing store-specific URLs.
     *
     * @param int   $productId     Product identifier
     * @param int[] $storeIds      Store identifiers
     * @param array $storeStatuses Store statuses indexed by store ID
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildStoreUrls(int $productId, array $storeIds, array $storeStatuses): array
    {
        $result = [];

        foreach ($storeIds as $storeId) {
            if ($storeId === Store::DEFAULT_STORE_ID) {
                continue;
            }

            try {
                $store = $this->storeManager->getStore($storeId);
            } catch (NoSuchEntityException $exception) {
                continue;
            }

            if (!$store->isActive()) {
                continue;
            }

            $url = $this->resolveProductUrl($productId, $store);

            if ($url === null) {
                continue;
            }

            $isPreview = ((int)($storeStatuses[$storeId] ?? Status::STATUS_ENABLED)) === Status::STATUS_DISABLED;

            if ($isPreview) {
                $url = $this->appendPreviewParameters($url, $productId, (int)$store->getId());
            }

            $result[] = [
                'store_id' => (int)$store->getId(),
                'store_code' => $store->getCode(),
                'store_name' => $store->getName(),
                'url' => $url,
                'sort_order' => (int)$store->getSortOrder(),
                'is_preview' => $isPreview,
            ];
        }

        usort(
            $result,
            static function (array $left, array $right): int {
                return $left['sort_order'] <=> $right['sort_order']
                    ?: strcmp($left['store_name'], $right['store_name']);
            }
        );

        return $result;
    }

    /**
     * Resolve product status per store view.
     *
     * @param int $productId
     * @param int[] $storeIds
     * @return array<int, int>
     */
    private function resolveProductStatuses(int $productId, array $storeIds): array
    {
        $statuses = [];

        foreach ($storeIds as $storeId) {
            $status = $this->productResource->getAttributeRawValue($productId, 'status', $storeId);
            if ($status === false || $status === null) {
                continue;
            }

            $statuses[(int)$storeId] = (int)$status;
        }

        return $statuses;
    }

    /**
     * Resolve an absolute product URL for a specific store view.
     *
     * @param int $productId
     * @param StoreInterface $store
     * @return string|null
     */
    private function resolveProductUrl(int $productId, StoreInterface $store): ?string
    {
        $requestPath = $this->resolveRequestPath($productId, (int)$store->getId());

        if ($requestPath) {
            return $this->normalizeUrl($requestPath, $store);
        }

        try {
            return $store->getUrl(
                'catalog/product/view',
                [
                    'id' => $productId,
                    '_nosid' => true,
                ]
            );
        } catch (LocalizedException $exception) {
            return null;
        }
    }

    /**
     * Attempt to resolve SEO friendly request path.
     *
     * @param int $productId
     * @param int $storeId
     * @return string|null
     */
    private function resolveRequestPath(int $productId, int $storeId): ?string
    {
        $filterData = [
            UrlRewrite::ENTITY_ID => $productId,
            UrlRewrite::ENTITY_TYPE => ProductUrlRewriteGenerator::ENTITY_TYPE,
            UrlRewrite::STORE_ID => $storeId,
            UrlRewrite::REDIRECT_TYPE => 0,
        ];

        $rewrites = $this->urlFinder->findAllByData($filterData);

        if (!$rewrites) {
            return null;
        }

        foreach ($rewrites as $rewrite) {
            $metadata = $rewrite->getMetadata();
            $categoryId = $metadata['category_id'] ?? null;

            if (!$categoryId) {
                return $rewrite->getRequestPath();
            }
        }

        $fallback = reset($rewrites);

        return $fallback instanceof UrlRewrite ? $fallback->getRequestPath() : null;
    }

    /**
     * Convert a request path into an absolute storefront URL.
     *
     * @param string $path
     * @param StoreInterface $store
     * @return string
     */
    private function normalizeUrl(string $path, StoreInterface $store): string
    {
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $baseUrl = rtrim($store->getBaseUrl(UrlInterface::URL_TYPE_LINK), '/');

        return $baseUrl . '/' . ltrim($path, '/');
    }

    /**
     * Append preview parameters to the resolved storefront URL.
     *
     * @param string $url
     * @param int $productId
     * @param int $storeId
     * @return string
     */
    private function appendPreviewParameters(string $url, int $productId, int $storeId): string
    {
        $token = $this->previewTokenManager->generate($productId, $storeId);
        $query = http_build_query(
            [
                ProductPreviewParameters::FLAG => 1,
                ProductPreviewParameters::TOKEN => $token,
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        $separator = strpos($url, '?') === false ? '?' : '&';

        return $url . $separator . $query;
    }
}
