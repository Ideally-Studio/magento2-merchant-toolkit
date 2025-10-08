<?php

namespace IdeallyStudio\MerchantToolkit\Ui\Component\Product\Form\Button;

use IdeallyStudio\MerchantToolkit\Model\ProductViewUrlResolver;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Block\Adminhtml\Product\Edit\Button\Generic;
use Magento\Ui\Component\Control\Container;

/**
 * Provides the "View/Preview on Store" button in the product editor.
 */
class ViewOnStore extends Generic
{
    /**
     * @var ProductViewUrlResolver
     */
    private $productViewUrlResolver;

    /**
     * ViewOnStore constructor.
     *
     * @param \Magento\Framework\View\Element\UiComponent\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param ProductViewUrlResolver $productViewUrlResolver
     */
    public function __construct(
        \Magento\Framework\View\Element\UiComponent\Context $context,
        \Magento\Framework\Registry $registry,
        ProductViewUrlResolver $productViewUrlResolver
    ) {
        parent::__construct($context, $registry);
        $this->productViewUrlResolver = $productViewUrlResolver;
    }

    /**
     * @inheritDoc
     */
    public function getButtonData()
    {
        $product = $this->getProduct();
        if (!$product || !$product->getId()) {
            return [];
        }

        if ((int)$product->getVisibility() === Visibility::VISIBILITY_NOT_VISIBLE) {
            return [];
        }

        $storeUrls = $this->buildStoreUrls($product);
        if (!$storeUrls) {
            return [];
        }

        if (count($storeUrls) === 1) {
            $storeData = reset($storeUrls);

            return [
                'label' => $storeData['is_preview'] ? __('Preview on Store') : __('View on Store'),
                'class' => 'action-secondary',
                'on_click' => $this->buildOnClick($storeData['href']),
                'sort_order' => 80,
            ];
        }

        $allPreview = $this->isPreviewOnly($storeUrls);

        $options = [];
        foreach ($storeUrls as $storeData) {
            $optionLabel = $storeData['is_preview']
                ? __('Preview on %1', $storeData['label'])
                : __('View on %1', $storeData['label']);

            $options[] = [
                'id_hard' => 'view_on_store_' . $storeData['store_id'],
                'label' => $optionLabel,
                'data_attribute' => [
                    'mage-init' => [
                        'buttonAdapter' => [
                            'actions' => [
                                [
                                    'targetName' => 'viewOnStore.target',
                                    'actionName' => 'viewOnStore',
                                    'params' => [
                                        $storeData['href'],
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
            ];
        }

        return [
            'label' => $allPreview ? __('Preview on Store') : __('View on Store'),
            'class' => 'view-on-store-parent-btn',
            'class_name' => Container::SPLIT_BUTTON,
            'sort_order' => 80,
            'options' => $options,
            'data_attribute' => [
                'mage-init' => [
                    'buttonAdapter' => [
                        'actions' => [
                            [
                                'targetName' => 'viewOnStore.main',
                                'actionName' => 'viewOnStoreBtn',
                                'params' => [
                                    true,
                                ]
                            ]
                        ]
                    ]
                ]
            ],
        ];
    }

    /**
     * Retrieve store-specific URLs for the product.
     *
     * @param ProductInterface $product
     * @return array
     */
    private function buildStoreUrls(ProductInterface $product): array
    {
        $storeUrls = $this->productViewUrlResolver->getStoreUrls($product);

        return array_map(
            static function (array $storeData): array {
                return [
                    'store_id' => $storeData['store_id'],
                    'label' => $storeData['store_name'],
                    'href' => $storeData['url'],
                    'is_preview' => !empty($storeData['is_preview']),
                ];
            },
            $storeUrls
        );
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

    /**
     * Determine if every store view link is a preview.
     *
     * @param array $storeUrls
     * @return bool
     */
    private function isPreviewOnly(array $storeUrls): bool
    {
        if (!$storeUrls) {
            return false;
        }

        foreach ($storeUrls as $storeData) {
            if (!empty($storeData['is_preview'])) {
                continue;
            }

            return false;
        }

        return true;
    }
}
