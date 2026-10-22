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
            ActorType::Admin => __('Admin'),
            ActorType::Integration => __('Integration'),
            ActorType::Customer => __('Customer'),
            ActorType::Guest => __('Guest'),
            ActorType::Cli => __('CLI'),
            ActorType::Cron => __('Cron'),
            ActorType::Anonymous => __('Anonymous'),
        };
    }
}
