<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Data\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCriterionInterface;

/**
 * Immutable search filter, created blank through SearchCriterionInterfaceFactory and filled through withTerm()
 */
class SearchCriterion implements SearchCriterionInterface
{
    /**
     * @var string
     */
    private string $key = self::TEXT_KEY;

    /**
     * @var string
     */
    private string $value = '';

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
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @inheritDoc
     */
    public function withTerm(string $key, string $value): SearchCriterionInterface
    {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new \InvalidArgumentException(sprintf('Malformed search key "%s".', $key));
        }

        $value = trim($value);
        $length = mb_strlen($value);
        if ($length === 0 || $length > self::MAX_VALUE_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('A search value is 1 to %d characters long, not %d.', self::MAX_VALUE_LENGTH, $length)
            );
        }

        $copy = clone $this;
        $copy->key = $key;
        $copy->value = $value;

        return $copy;
    }
}
