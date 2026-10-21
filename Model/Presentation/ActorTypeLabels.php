<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Presentation;

use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Magento\Framework\Phrase;

/**
 * What people call each kind of actor
 */
class ActorTypeLabels
{
    /**
     * Label of a kind of actor
     *
     * @param ActorType $type
     * @return Phrase
     */
    public function label(ActorType $type): Phrase
    {
        return match ($type) {
            ActorType::Admin => __('admin'),
            ActorType::Integration => __('integration'),
            ActorType::Customer => __('customer'),
            ActorType::Guest => __('guest'),
            ActorType::Cli => __('command line'),
            ActorType::Cron => __('cron'),
            ActorType::Anonymous => __('anonymous'),
        };
    }
}
