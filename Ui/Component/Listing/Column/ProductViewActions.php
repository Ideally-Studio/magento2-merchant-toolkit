<?php

namespace IdeallyStudio\MerchantToolkit\Ui\Component\Listing\Column;

use IdeallyStudio\MerchantToolkit\Model\ProductViewUrlResolver;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Adds a "View" actions column to the product grid.
 */
class ProductViewActions extends Column
{
    /**
     * @var ProductViewUrlResolver
     */
    private $productViewUrlResolver;

    /**
     * @var Escaper
     */
    private $escaper;

    /**
     * ProductViewActions constructor.
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param ProductViewUrlResolver $productViewUrlResolver
     * @param Escaper $escaper
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        ProductViewUrlResolver $productViewUrlResolver,
        Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->productViewUrlResolver = $productViewUrlResolver;
        $this->escaper = $escaper;
    }

    /**
     * @inheritDoc
     */
    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $columnName = $this->getData('name');

        foreach ($dataSource['data']['items'] as $index => &$item) {
            if (!isset($item['entity_id'])) {
                continue;
            }

            $productId = (int)$item['entity_id'];
            if ($productId <= 0) {
                continue;
            }

            $storeUrls = $this->productViewUrlResolver->getStoreUrlsById($productId);
            if (!$storeUrls) {
                continue;
            }

            $item[$columnName] = $this->buildActions(
                $storeUrls,
                $item,
                $index
            );
        }

        return $dataSource;
    }

    /**
     * Build the actions array consumed by the UI component.
     *
     * @param array $storeUrls
     * @param array $item
     * @param int $rowIndex
     * @return array<string, array<string, mixed>>
     */
    private function buildActions(array $storeUrls, array $item, int $rowIndex): array
    {
        $actions = [];
        $isSingle = count($storeUrls) === 1;
        $productName = isset($item['name']) ? $this->escaper->escapeHtml($item['name']) : '';

        foreach ($storeUrls as $position => $storeData) {
            $actionKey = $isSingle && $position === 0
                ? 'view'
                : 'view_store_' . $storeData['store_id'];

            $label = $isSingle
                ? (string)__('View')
                : (string)__('View (%1)', $storeData['store_name']);

            $actions[$actionKey] = [
                'href' => $storeData['url'],
                'label' => $label,
                'target' => '_blank',
                'hidden' => false,
                'rowIndex' => $rowIndex,
                'store_id' => $storeData['store_id'],
            ];

            if ($productName !== '') {
                $actions[$actionKey]['ariaLabel'] = (string)__('View %1', $productName);
            }
        }

        return $actions;
    }
}
