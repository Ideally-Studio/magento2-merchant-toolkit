<?php

namespace IdeallyStudio\MerchantToolkit\Model;

use Magento\Cms\Model\PageFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Store;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use const FILTER_VALIDATE_URL;

/**
 * Resolves storefront URLs for CMS pages with respect to store assignments.
 */
class CmsPageViewUrlResolver
{
    /**
     * @var PageFactory
     */
    private $pageFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * CmsPageViewUrlResolver constructor.
     *
     * @param PageFactory $pageFactory
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        PageFactory $pageFactory,
        StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection
    ) {
        $this->pageFactory = $pageFactory;
        $this->storeManager = $storeManager;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Retrieve store-specific URLs for the given page ID.
     *
     * @param int $pageId
     * @return array<int, array<string, mixed>>
     */
    public function getStoreUrlsById(int $pageId): array
    {
        if ($pageId <= 0) {
            return [];
        }

        $storeIds = $this->resolveStoreIds($pageId);
        if (!$storeIds) {
            return [];
        }

        $urls = [];
        foreach ($storeIds as $storeId) {
            if ($storeId === Store::DEFAULT_STORE_ID) {
                continue;
            }

            try {
                $store = $this->storeManager->getStore($storeId);
            } catch (NoSuchEntityException $exception) {
                continue;
            }

            if (!$store->isActive() || $store->isAdmin()) {
                continue;
            }

            $identifier = $this->loadIdentifierForStore($pageId, $storeId);
            if (!$identifier) {
                continue;
            }

            $urls[] = [
                'store_id' => (int)$store->getId(),
                'store_code' => $store->getCode(),
                'store_name' => $store->getName(),
                'url' => $this->buildUrl($identifier, $store),
                'sort_order' => (int)$store->getSortOrder(),
            ];
        }

        usort(
            $urls,
            static function (array $left, array $right): int {
                return $left['sort_order'] <=> $right['sort_order']
                    ?: strcmp($left['store_name'], $right['store_name']);
            }
        );

        return $urls;
    }

    /**
     * Determine the store IDs related to the page, including All Store Views.
     *
     * @param int $pageId
     * @return int[]
     */
    private function resolveStoreIds(int $pageId): array
    {
        $page = $this->pageFactory->create();
        $page->load($pageId);
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('cms_page_store');

        $storeIds = $connection->fetchCol(
            $connection->select()
                ->from($table, 'store_id')
                ->where('row_id = ?', $page->getRowId())
        );

        $storeIds = array_values(array_unique(array_map('intval', $storeIds)));

        if (!$storeIds || in_array(Store::DEFAULT_STORE_ID, $storeIds, true)) {
            $storeIds = [];
            foreach ($this->storeManager->getStores(false) as $store) {
                if ($store->isAdmin()) {
                    continue;
                }
                $storeIds[] = (int)$store->getId();
            }
        }

        return array_values(array_unique($storeIds));
    }

    /**
     * Load the page identifier scoped to the provided store.
     *
     * @param int $pageId
     * @param int $storeId
     * @return string|null
     */
    private function loadIdentifierForStore(int $pageId, int $storeId): ?string
    {
        $page = $this->pageFactory->create();
        $page->setStoreId($storeId);
        $page->load($pageId);

        if (!$page->getId()) {
            return null;
        }

        $identifier = (string)$page->getIdentifier();

        return $identifier !== '' ? $identifier : null;
    }

    /**
     * Build an absolute frontend URL from the page identifier.
     *
     * @param string $identifier
     * @param \Magento\Store\Model\Store $store
     * @return string
     */
    private function buildUrl(string $identifier, Store $store): string
    {
        if (filter_var($identifier, FILTER_VALIDATE_URL)) {
            return $identifier;
        }

        return $store->getUrl(
            null,
            [
                '_direct' => ltrim($identifier, '/'),
                '_nosid' => true,
            ]
        );
    }
}
