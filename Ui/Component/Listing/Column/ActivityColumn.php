<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Ui\Component\Listing\Column;

use Hryvinskyi\ActivityLog\Model\Channel\ChannelPresenter;
use Hryvinskyi\ActivityLog\Model\Presentation\DurationScale;
use Hryvinskyi\ActivityLog\Model\Source\OutcomeOptions;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Hands the activity grid's custom columns what they draw with: channel labels and colours, the duration scale,
 * outcome labels and the store timezone
 */
class ActivityColumn extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param ChannelPresenter $channelPresenter
     * @param DurationScale $durationScale
     * @param OutcomeOptions $outcomeOptions
     * @param TimezoneInterface $timezone
     * @param array<mixed> $components
     * @param array<mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly ChannelPresenter $channelPresenter,
        private readonly DurationScale $durationScale,
        private readonly OutcomeOptions $outcomeOptions,
        private readonly TimezoneInterface $timezone,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @inheritDoc
     */
    public function prepare(): void
    {
        $config = $this->getData('config');
        $config = is_array($config) ? $config : [];
        $config['channels'] = $this->channelPresenter->present();
        $config['durationScale'] = $this->durationScale->levels();
        $config['timezone'] = $this->timezone->getConfigTimezone();

        $outcomes = [];
        foreach ($this->outcomeOptions->toOptionArray() as $option) {
            $outcomes[$option['value']] = (string) $option['label'];
        }
        $config['outcomes'] = $outcomes;

        $this->setData('config', $config);

        parent::prepare();
    }
}
