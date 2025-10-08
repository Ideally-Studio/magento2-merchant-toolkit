<?php

namespace IdeallyStudio\MerchantToolkit\Plugin\Catalog\Helper\Product;

use IdeallyStudio\MerchantToolkit\Model\ProductPreviewParameters;
use IdeallyStudio\MerchantToolkit\Model\ProductPreviewTokenManager;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Product as ProductHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Enables temporary storefront access to disabled products when a valid preview token is supplied.
 */
class PreviewVisibilityPlugin
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ProductPreviewTokenManager
     */
    private $previewTokenManager;

    /**
     * @param RequestInterface $request
     * @param StoreManagerInterface $storeManager
     * @param ProductRepositoryInterface $productRepository
     * @param ProductPreviewTokenManager $previewTokenManager
     */
    public function __construct(
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        ProductRepositoryInterface $productRepository,
        ProductPreviewTokenManager $previewTokenManager
    ) {
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->previewTokenManager = $previewTokenManager;
    }

    /**
     * Allow disabled products to render when accessed with a valid preview token.
     *
     * @param ProductHelper $subject
     * @param callable $proceed
     * @param int|string|Product $product
     * @param string $where
     * @return bool
     */
    public function aroundCanShow(
        ProductHelper $subject,
        callable $proceed,
        $product,
        $where = 'catalog'
    ): bool {
        if (!$this->isPreviewRequest()) {
            return $proceed($product, $where);
        }

        $productInstance = $this->resolveProductInstance($product);
        if (!$productInstance) {
            return $proceed($product, $where);
        }

        if ((int)$productInstance->getStatus() !== Status::STATUS_DISABLED) {
            return $proceed($productInstance, $where);
        }

        $token = (string)$this->request->getParam(ProductPreviewParameters::TOKEN);
        $storeId = (int)$this->storeManager->getStore()->getId();

        if (!$this->previewTokenManager->isValid($token, (int)$productInstance->getId(), $storeId)) {
            return $proceed($productInstance, $where);
        }

        $productInstance->setStatus(Status::STATUS_ENABLED);

        return $proceed($productInstance, $where);
    }

    /**
     * Determine whether the current request is a preview request.
     *
     * @return bool
     */
    private function isPreviewRequest(): bool
    {
        $flag = $this->request->getParam(ProductPreviewParameters::FLAG);
        $token = $this->request->getParam(ProductPreviewParameters::TOKEN);

        return !empty($flag) && !empty($token);
    }

    /**
     * Normalize the incoming product argument to an instance when possible.
     *
     * @param mixed $product
     * @return Product|null
     */
    private function resolveProductInstance($product): ?Product
    {
        if ($product instanceof Product) {
            return $product;
        }

        if (is_numeric($product)) {
            try {
                return $this->productRepository->getById(
                    (int)$product,
                    false,
                    (int)$this->storeManager->getStore()->getId()
                );
            } catch (\Magento\Framework\Exception\NoSuchEntityException $exception) {
                return null;
            }
        }

        return null;
    }
}
