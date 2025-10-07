<?php

namespace IdeallyStudio\MerchantToolkit\Ui\Component\Cms\Page\Form\Button;

use IdeallyStudio\MerchantToolkit\Model\CmsPageViewUrlResolver;
use Magento\Cms\Block\Adminhtml\Page\Edit\GenericButton;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\Ui\Component\Control\Container;

/**
 * Provides the "View Page" action button within the CMS page editor.
 */
class ViewOnStore extends GenericButton implements ButtonProviderInterface
{
    /**
     * @var CmsPageViewUrlResolver
     */
    private $cmsPageViewUrlResolver;

    /**
     * ViewOnStore constructor.
     *
     * @param \Magento\Backend\Block\Widget\Context $context
     * @param \Magento\Cms\Api\PageRepositoryInterface $pageRepository
     * @param CmsPageViewUrlResolver $cmsPageViewUrlResolver
     */
    public function __construct(
        \Magento\Backend\Block\Widget\Context $context,
        \Magento\Cms\Api\PageRepositoryInterface $pageRepository,
        CmsPageViewUrlResolver $cmsPageViewUrlResolver
    ) {
        parent::__construct($context, $pageRepository);
        $this->cmsPageViewUrlResolver = $cmsPageViewUrlResolver;
    }

    /**
     * @inheritDoc
     */
    public function getButtonData()
    {
        $pageId = (int)$this->getPageId();
        if (!$pageId) {
            return [];
        }

        $storeUrls = $this->cmsPageViewUrlResolver->getStoreUrlsById($pageId);
        if (!$storeUrls) {
            return [];
        }

        if (count($storeUrls) === 1) {
            $storeData = reset($storeUrls);

            return [
                'label' => __('View Page'),
                'on_click' => $this->buildOnClick($storeData['url']),
                'sort_order' => 80,
            ];
        }

        $options = [];
        foreach ($storeUrls as $storeData) {
            $options[] = [
                'id_hard' => 'view_cms_on_store_' . $storeData['store_id'],
                'label' => $storeData['store_name'],
                'data_attribute' => [
                    'mage-init' => [
                        'buttonAdapter' => [
                            'actions' => [
                                [
                                    'targetName' => 'viewOnStore.target',
                                    'actionName' => 'viewOnStore',
                                    'params' => [
                                        $storeData['url'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        }

        $firstStore = reset($storeUrls);

        return [
            'label' => __('View Page'),
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
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build the JavaScript handler for opening the storefront page.
     *
     * @param string $url
     * @return string
     */
    private function buildOnClick(string $url): string
    {
        return sprintf('window.open(%s, "_blank");', json_encode($url));
    }
}
