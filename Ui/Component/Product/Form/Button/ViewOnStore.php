<?php

namespace IdeallyStudio\MerchantToolkit\Ui\Component\Product\Form\Button;

use IdeallyStudio\MerchantToolkit\Model\ProductViewUrlResolver;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Block\Adminhtml\Product\Edit\Button\Generic;
use Magento\Ui\Component\Control\Container;

/**
 * Provides the "View on Store" button in the product editor.
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

        $storeUrls = $this->buildStoreUrls($product);
        if (!$storeUrls) {
            return [];
        }

        if (count($storeUrls) === 1) {
            $storeData = reset($storeUrls);

            return [
                'label' => __('View on Store'),
                'class' => 'action-secondary',
                'on_click' => $this->buildOnClick($storeData['href']),
                'sort_order' => 80,
            ];
        }

        $firstStore = reset($storeUrls);
        $options = [];
        foreach ($storeUrls as $storeData) {
            $options[] = [
                'id_hard' => 'view_on_store_' . $storeData['store_id'],
                'label' => $storeData['label'],
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
            'label' => __('View on Store'),
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
}
