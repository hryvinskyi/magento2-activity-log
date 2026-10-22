<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Adds the detail page URL to each row; "Show related" is a filter the column applies in the browser
 */
class ActivityActions extends Column
{
    public const URL_PATH_VIEW = 'hryvinskyi_activitylog/activity/view';

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array<mixed> $components
     * @param array<mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Add the detail page link to each row
     *
     * @param array<mixed> $dataSource
     * @return array<mixed>
     */
    public function prepareDataSource(array $dataSource): array
    {
        $data = $dataSource['data'] ?? null;
        if (!is_array($data) || !is_array($data['items'] ?? null)) {
            return $dataSource;
        }

        $items = $data['items'];
        foreach ($items as $index => $item) {
            if (is_array($item) && is_numeric($item['entity_id'] ?? null)) {
                $item['view_url'] = $this->urlBuilder->getUrl(self::URL_PATH_VIEW, ['id' => (int) $item['entity_id']]);
                $items[$index] = $item;
            }
        }

        $data['items'] = $items;
        $dataSource['data'] = $data;

        return $dataSource;
    }
}
