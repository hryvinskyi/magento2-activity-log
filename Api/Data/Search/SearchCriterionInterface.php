<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Data\Search;

/**
 * One filter of an activity search, a "key: value" chip
 *
 * Create one through SearchCriterionInterfaceFactory, then give it its key and value with withTerm(). The term is
 * never a factory argument: Magento's object manager reads an array handed to a factory as DI configuration, and the
 * term comes from the person searching. A criterion straight from the factory is blank (key "text", empty value), and
 * a search request refuses a blank criterion.
 *
 * @api
 */
interface SearchCriterionInterface
{
    /**
     * What a key looks like: a lowercase letter, then up to 31 lowercase letters or underscores
     */
    public const KEY_PATTERN = '/^[a-z][a-z_]{0,31}$/D';

    /**
     * Longest value, in characters, after trimming
     */
    public const MAX_VALUE_LENGTH = 255;

    /**
     * The key plain words are searched under
     */
    public const TEXT_KEY = 'text';

    /**
     * The key, such as "by" or "text"
     *
     * @return string
     */
    public function getKey(): string;

    /**
     * The value, trimmed; empty only on a blank criterion
     *
     * @return string
     */
    public function getValue(): string;

    /**
     * A copy with this key and value; this criterion is left as it is
     *
     * @param string $key Matches KEY_PATTERN
     * @param string $value Trimmed, then 1 to MAX_VALUE_LENGTH characters
     * @return SearchCriterionInterface
     * @throws \InvalidArgumentException When the key is malformed or the trimmed value is empty or too long
     */
    public function withTerm(string $key, string $value): SearchCriterionInterface;
}
