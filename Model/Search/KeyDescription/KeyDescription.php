<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\KeyDescription;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCriterionInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchKeyControl;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchKeyGroup;
use Hryvinskyi\ActivityLog\Api\Search\SearchKeyDescriptionInterface;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * A key description configured in di.xml: label and hint arrive translated (translate="true"), options come from an
 * option source when the control is a select, the suggestion group is one of SearchKeyGroup's codes ("other" unless
 * given)
 *
 * Modules that serve keys declare their descriptions as virtual types of this class.
 *
 * @api
 */
class KeyDescription implements SearchKeyDescriptionInterface
{
    /**
     * @var SearchKeyControl
     */
    private SearchKeyControl $control;

    /**
     * @var SearchKeyGroup
     */
    private SearchKeyGroup $group;

    /**
     * @var array<string, string>
     */
    private array $optionTargets = [];

    /**
     * @param string $key
     * @param string $label
     * @param string $hint
     * @param string $control "text" or "select"
     * @param int $sortOrder
     * @param OptionSourceInterface|null $options Required for a select
     * @param string $group A SearchKeyGroup code
     * @param bool $matchesFieldNames Whether the value names fields
     * @param bool $inPanel Whether the filters panel has a control for the key
     * @param array<string,string> $optionTargets The key each option of the select stands for, by option value
     * @param string $companionLabel Name of the text box beside the select; empty for none
     * @param string $companionHint What that text box takes
     * @throws \InvalidArgumentException When the key, the control or the group is malformed, a select has no
     *     options, or a companion box or option targets are set for a key that cannot have them
     */
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $hint,
        string $control = 'text',
        private readonly int $sortOrder = 0,
        private readonly ?OptionSourceInterface $options = null,
        string $group = 'other',
        private readonly bool $matchesFieldNames = false,
        private readonly bool $inPanel = true,
        array $optionTargets = [],
        private readonly string $companionLabel = '',
        private readonly string $companionHint = ''
    ) {
        if (preg_match(SearchCriterionInterface::KEY_PATTERN, $key) !== 1) {
            throw new \InvalidArgumentException(sprintf('Malformed search key "%s".', $key));
        }

        $this->control = SearchKeyControl::tryFrom($control)
            ?? throw new \InvalidArgumentException(sprintf('Unknown filter control "%s".', $control));
        if ($this->control === SearchKeyControl::Select && $options === null) {
            throw new \InvalidArgumentException(sprintf('The select of key "%s" has no options.', $key));
        }
        $this->group = SearchKeyGroup::tryFrom($group)
            ?? throw new \InvalidArgumentException(sprintf('Unknown suggestion group "%s".', $group));
        if ($companionLabel !== '' && $this->control !== SearchKeyControl::Select) {
            throw new \InvalidArgumentException(sprintf('Key "%s" has a companion box but no select.', $key));
        }
        if ($optionTargets !== [] && $companionLabel === '') {
            throw new \InvalidArgumentException(sprintf('Key "%s" has option targets but no companion box.', $key));
        }
        foreach ($optionTargets as $option => $target) {
            if (preg_match(SearchCriterionInterface::KEY_PATTERN, $target) !== 1) {
                throw new \InvalidArgumentException(
                    sprintf('Option "%s" of key "%s" names a malformed key.', (string) $option, $key)
                );
            }
            $this->optionTargets[(string) $option] = $target;
        }
    }

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @inheritDoc
     */
    public function getHint(): string
    {
        return $this->hint;
    }

    /**
     * @inheritDoc
     */
    public function getControl(): SearchKeyControl
    {
        return $this->control;
    }

    /**
     * @inheritDoc
     */
    public function getOptions(): array
    {
        if ($this->control !== SearchKeyControl::Select || $this->options === null) {
            return [];
        }

        $options = [];
        foreach ($this->options->toOptionArray() as $option) {
            if (is_array($option) && is_scalar($option['value'] ?? null) && isset($option['label'])) {
                $label = $option['label'];
                $options[] = [
                    'value' => (string) $option['value'],
                    'label' => is_scalar($label) || $label instanceof \Stringable ? (string) $label : '',
                ];
            }
        }

        return $options;
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * @inheritDoc
     */
    public function getGroup(): SearchKeyGroup
    {
        return $this->group;
    }

    /**
     * @inheritDoc
     */
    public function matchesFieldNames(): bool
    {
        return $this->matchesFieldNames;
    }

    /**
     * @inheritDoc
     */
    public function isInPanel(): bool
    {
        return $this->inPanel;
    }

    /**
     * @inheritDoc
     */
    public function getOptionTargets(): array
    {
        return $this->optionTargets;
    }

    /**
     * @inheritDoc
     */
    public function getCompanionLabel(): string
    {
        return $this->companionLabel;
    }

    /**
     * @inheritDoc
     */
    public function getCompanionHint(): string
    {
        return $this->companionHint;
    }
}
