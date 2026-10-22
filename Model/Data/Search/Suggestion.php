<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Data\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCriterionInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SuggestionInterface;

/**
 * Immutable suggestion, created blank through SuggestionInterfaceFactory and filled through the with…() methods
 */
class Suggestion implements SuggestionInterface
{
    /**
     * @var string
     */
    private string $value = '';

    /**
     * @var string
     */
    private string $label = '';

    /**
     * @var string
     */
    private string $hint = '';

    /**
     * @var int|null
     */
    private ?int $count = null;

    /**
     * @inheritDoc
     */
    public function getValue(): string
    {
        return $this->value;
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
    public function getCount(): ?int
    {
        return $this->count;
    }

    /**
     * @inheritDoc
     */
    public function withValue(string $value, ?string $label = null): SuggestionInterface
    {
        $value = trim($value);
        $length = mb_strlen($value);
        if ($length === 0 || $length > SearchCriterionInterface::MAX_VALUE_LENGTH) {
            throw new \InvalidArgumentException(sprintf(
                'A suggested value is 1 to %d characters long, not %d.',
                SearchCriterionInterface::MAX_VALUE_LENGTH,
                $length
            ));
        }

        $label = trim((string) $label);
        $copy = clone $this;
        $copy->value = $value;
        $copy->label = $label === '' ? $value : $label;

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withHint(string $hint): SuggestionInterface
    {
        $copy = clone $this;
        $copy->hint = trim($hint);

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withCount(?int $count): SuggestionInterface
    {
        if ($count !== null && $count < 0) {
            throw new \InvalidArgumentException(sprintf('A count is 0 or more, not %d.', $count));
        }

        $copy = clone $this;
        $copy->count = $count;

        return $copy;
    }
}
