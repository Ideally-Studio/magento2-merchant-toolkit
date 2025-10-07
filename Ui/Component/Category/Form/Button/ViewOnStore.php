<?php

namespace IdeallyStudio\MerchantToolkit\Ui\Component\Category\Form\Button;

use Magento\Catalog\Block\Adminhtml\Category\AbstractCategory;
use Magento\Catalog\Model\Category;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\Store\Model\Store;
use Magento\Framework\UrlInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator;
use Magento\Store\Model\ScopeInterface;
use const FILTER_VALIDATE_URL;

/**
 * Adds a "View on Store" button to the category editor.
 */
class ViewOnStore extends AbstractCategory implements ButtonProviderInterface
{
    /**
     * @var UrlFinderInterface
     */
    private $urlFinder;

    /**
     * ViewOnStore constructor.
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Catalog\Model\ResourceModel\Category\Tree $categoryTree
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Catalog\Model\CategoryFactory $categoryFactory
     * @param UrlFinderInterface $urlFinder
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Catalog\Model\ResourceModel\Category\Tree $categoryTree,
        \Magento\Framework\Registry $registry,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory,
        UrlFinderInterface $urlFinder,
        array $data = []
    ) {
        parent::__construct($context, $categoryTree, $registry, $categoryFactory, $data);
        $this->urlFinder = $urlFinder;
    }

    /**
     * @inheritDoc
     */
    public function getButtonData()
    {
        $category = $this->getCategory();

        if (!$category || !$category->getId() || !$this->hasStoreRootCategory()) {
            return [];
        }

        $url = $this->getCategoryUrl($category);

        if (!$url) {
            return [];
        }

        return [
            'label' => __('View on Store'),
            'class' => 'action-secondary',
            'on_click' => $this->buildOnClick($url),
            'sort_order' => 80,
        ];
    }

    /**
     * Calculate the storefront URL for the provided category.
     *
     * @param Category $category
     * @return string|null
     */
    private function getCategoryUrl(Category $category): ?string
    {
        $store = $this->resolveStore($category);

        if ($store === null) {
            return null;
        }

        if (!$this->categoryBelongsToStore($category, (int)$store->getRootCategoryId())) {
            return null;
        }

        $categoryForStore = clone $category;
        $categoryForStore->setStoreId((int)$store->getId());
        $categoryForStore->setStore($store);
        $categoryForStore->unsetData('url');

        $requestPath = $this->resolveRequestPath($categoryForStore, $store);

        if ($requestPath) {
            return $this->normalizeUrl($requestPath, $store);
        }

        $urlPath = (string)$categoryForStore->getUrlPath();
        if ($urlPath) {
            return $this->normalizeUrl($urlPath, $store);
        }

        $urlKey = (string)$categoryForStore->getUrlKey();
        if ($urlKey) {
            $suffix = (string)$this->_scopeConfig->getValue(
                'catalog/seo/category_url_suffix',
                ScopeInterface::SCOPE_STORE,
                $store->getId()
            );
            $path = $urlKey . $suffix;

            return $this->normalizeUrl($path, $store);
        }

        return null;
    }

    /**
     * Resolve the store view that should be used for the "View" action.
     *
     * @param Category $category
     * @return Store|null
     * @throws LocalizedException
     */
    private function resolveStore(Category $category): ?Store
    {
        $storeParam = $this->getRequest()->getParam('store', null);
        if ($storeParam !== null && $storeParam !== '') {
            try {
                $store = $this->_storeManager->getStore($storeParam);
            } catch (NoSuchEntityException $e) {
                throw new LocalizedException(__(sprintf('Store with ID %s doesn\'t exists', $storeParam)));
            }
            if ($store->isActive() && !$store->isAdmin()) {
                return $store;
            }
        }

        $rootCategoryId = $this->resolveRootCategoryId($category);
        if ($rootCategoryId) {
            $candidate = null;
            foreach ($this->_storeManager->getStores(false) as $storeView) {
                if ($storeView->isAdmin() || !$storeView->isActive()) {
                    continue;
                }

                if ((int)$storeView->getRootCategoryId() !== $rootCategoryId) {
                    continue;
                }

                if ($candidate === null || $this->isDefaultStoreOfGroup($storeView)) {
                    $candidate = $storeView;
                }

                if ($this->isDefaultStoreOfGroup($storeView)) {
                    break;
                }
            }

            if ($candidate !== null) {
                return $candidate;
            }
        }

        $defaultStore = $this->_storeManager->getDefaultStoreView();
        if ($defaultStore && $defaultStore->isActive()) {
            return $defaultStore;
        }

        return null;
    }

    /**
     * Check if the category belongs to the provided root category.
     *
     * @param Category $category
     * @param int $rootCategoryId
     * @return bool
     */
    private function categoryBelongsToStore(Category $category, int $rootCategoryId): bool
    {
        $pathIds = array_map('intval', (array)$category->getPathIds());

        if (!$rootCategoryId) {
            return true;
        }

        return in_array($rootCategoryId, $pathIds, true);
    }

    /**
     * Resolve the root category ID from the category path.
     *
     * @param Category $category
     * @return int
     */
    private function resolveRootCategoryId(Category $category): int
    {
        $pathIds = array_values(array_map('intval', (array)$category->getPathIds()));

        if (isset($pathIds[1])) {
            return $pathIds[1];
        }

        return $pathIds[0] ?? 0;
    }

    /**
     * Check whether the store is the default store of its group.
     *
     * @param Store $store
     * @return bool
     */
    private function isDefaultStoreOfGroup(Store $store): bool
    {
        $group = $store->getGroup();

        if (!$group) {
            return false;
        }

        return (int)$group->getDefaultStoreId() === (int)$store->getId();
    }

    /**
     * Convert a path into an absolute storefront URL.
     *
     * @param string $url
     * @param Store $store
     * @return string
     */
    private function normalizeUrl(string $url, Store $store): string
    {
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        $baseUrl = rtrim($store->getBaseUrl(UrlInterface::URL_TYPE_LINK), '/');

        return $baseUrl . '/' . ltrim($url, '/');
    }

    /**
     * Attempt to resolve an SEO-friendly request path for the category.
     *
     * @param Category $category
     * @param Store $store
     * @return string|null
     */
    private function resolveRequestPath(Category $category, Store $store): ?string
    {
        $filterData = [
            UrlRewrite::ENTITY_ID => (int)$category->getId(),
            UrlRewrite::ENTITY_TYPE => CategoryUrlRewriteGenerator::ENTITY_TYPE,
            UrlRewrite::STORE_ID => (int)$store->getId(),
            UrlRewrite::REDIRECT_TYPE => 0,
        ];

        $rewrite = $this->urlFinder->findOneByData($filterData);

        if ($rewrite && $rewrite->getRequestPath()) {
            return $rewrite->getRequestPath();
        }

        return null;
    }

    /**
     * Build the JavaScript on-click handler.
     *
     * @param string $url
     * @return string
     */
    private function buildOnClick(string $url): string
    {
        return sprintf('window.open(%s, "_blank");', json_encode($url));
    }
}
