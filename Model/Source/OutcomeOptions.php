<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Source;

use Hryvinskyi\ActivityLog\Api\Data\Outcome;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

/**
 * The outcomes, for filters
 */
class OutcomeOptions implements OptionSourceInterface
{
    /**
     * @inheritDoc
     *
     * @return list<array{value: string, label: Phrase}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (Outcome::cases() as $outcome) {
            $options[] = ['value' => $outcome->value, 'label' => $this->label($outcome)];
        }

        return $options;
    }

    /**
     * Label people read for an outcome
     *
     * @param Outcome $outcome
     * @return Phrase
     */
    public function label(Outcome $outcome): Phrase
    {
        return match ($outcome) {
            Outcome::Success => __('Success'),
            Outcome::Failure => __('Failure'),
            Outcome::Denied => __('Denied'),
        };
    }
}
