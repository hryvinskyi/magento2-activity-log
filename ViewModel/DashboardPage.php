<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\ViewModel;

use Hryvinskyi\ActivityLog\Api\ConfigInterface;
use Hryvinskyi\ActivityLog\Api\Data\DashboardQueryInterface;
use Hryvinskyi\ActivityLog\Api\DashboardWidgetInterface;
use Hryvinskyi\ActivityLog\Model\Channel\ChannelPresenter;
use Hryvinskyi\ActivityLog\Model\Dashboard\QueryResolver;
use Hryvinskyi\ActivityLog\Model\Dashboard\WidgetPool;
use Hryvinskyi\ActivityLog\Model\Data\DashboardQuery;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * What the dashboard page prints: the toolbar, the widget frames, and the config its script reads
 */
class DashboardPage implements ArgumentInterface
{
    /**
     * @var DashboardQueryInterface|null
     */
    private ?DashboardQueryInterface $initialQuery = null;

    /**
     * @param ConfigInterface $config
     * @param ChannelPresenter $channelPresenter
     * @param QueryResolver $queryResolver
     * @param WidgetPool $widgetPool
     * @param UrlInterface $urlBuilder
     * @param FormKey $formKey
     * @param JsonHexTag $jsonHexTag
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly ChannelPresenter $channelPresenter,
        private readonly QueryResolver $queryResolver,
        private readonly WidgetPool $widgetPool,
        private readonly UrlInterface $urlBuilder,
        private readonly FormKey $formKey,
        private readonly JsonHexTag $jsonHexTag
    ) {
    }

    /**
     * Whether activities are being recorded now
     *
     * @return bool
     */
    public function isRecording(): bool
    {
        return $this->config->isEnabled();
    }

    /**
     * The activity log settings page
     *
     * The activity grid
     *
     * The activity log settings page
     *
     * @return string
     */
    public function settingsUrl(): string
    {
        return $this->urlBuilder->getUrl('adminhtml/system_config/edit', ['section' => 'hryvinskyi_activity_log']);
    }

    /**
     * The activity grid
     *
     * @return string
     */
    public function gridUrl(): string
    {
        return $this->urlBuilder->getUrl('activitylog/activity/index');
    }

    /**
     * Period buttons
     *
     * @return list<array{days: int, label: string, active: bool}>
     */
    public function periods(): array
    {
        $periods = [];
        foreach (DashboardQuery::PERIODS as $days) {
            $periods[] = [
                'days' => $days,
                'label' => (string) __('%1 days', $days),
                'active' => $days === $this->initialQuery()->getPeriodDays(),
            ];
        }

        return $periods;
    }

    /**
     * Channel chips
     *
     * @return list<array{code: string, label: string, chart: string, active: bool}>
     */
    public function channels(): array
    {
        $active = $this->initialQuery()->getChannels();
        $chips = [];
        foreach ($this->channelPresenter->present() as $code => $channel) {
            $chips[] = [
                'code' => $code,
                'label' => $channel['label'],
                'chart' => $channel['chart'],
                'active' => in_array($code, $active, true),
            ];
        }

        return $chips;
    }

    /**
     * Widgets the admin user may see, in order
     *
     * @return list<DashboardWidgetInterface>
     */
    public function widgets(): array
    {
        return $this->widgetPool->visible();
    }

    /**
     * The script config as JSON safe to print inside a script element
     *
     * @return string
     */
    public function scriptConfigJson(): string
    {
        return (string) $this->jsonHexTag->serialize($this->scriptConfig());
    }

    /**
     * The period and channels the admin looked at last time
     *
     * Config the dashboard script reads
     *
     * @return array<string, mixed>
     */
    public function scriptConfig(): array
    {
        $query = $this->initialQuery();
        $widgets = [];
        foreach ($this->widgets() as $widget) {
            $widgets[$widget->getCode()] = ['viewType' => $widget->getViewType()->value];
        }

        return [
            'dataUrl' => $this->urlBuilder->getUrl('activitylog/dashboard/data'),
            'preferenceUrl' => $this->urlBuilder->getUrl('activitylog/dashboard/savePreference'),
            'formKey' => $this->formKey->getFormKey(),
            'periodDays' => $query->getPeriodDays(),
            'channels' => $query->getChannels(),
            'widgets' => $widgets,
            'timeoutMs' => 20000,
            'wording' => [
                'vsPrevious' => (string) __('vs previous %1 days'),
                'noData' => (string) __('No data for this period'),
                'emptyTitle' => (string) __('No activity in this period'),
                'emptyHint' => (string) __('Try a longer period or switch more channels on.'),
                'error' => (string) __('This widget could not be loaded.'),
                'loadError' => (string) __('Dashboard data could not be loaded.'),
                'retry' => (string) __('Retry'),
                'showTable' => (string) __('Table'),
                'showChart' => (string) __('Chart'),
                'total' => (string) __('Total'),
                'label' => (string) __('Label'),
                'updated' => (string) __('updated %1'),
            ],
        ];
    }

    /**
     * The period and channels the admin looked at last time
     *
     * @return DashboardQueryInterface
     */
    private function initialQuery(): DashboardQueryInterface
    {
        return $this->initialQuery ??= $this->queryResolver->resolve(null, null);
    }
}
