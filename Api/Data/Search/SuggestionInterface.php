<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Data\Search;

/**
 * A value the search bar offers for a key while the admin types: what the chip will hold and how the list shows it
 *
 * Create one through SuggestionInterfaceFactory, then fill it with the with…() methods, each of which returns a copy.
 * The value is never a factory argument: Magento's object manager reads an array handed to a factory as DI
 * configuration, and the value comes from recorded data. A suggestion straight from the factory has no value, which
 * withValue() must give it.
 *
 * @api
 */
interface SuggestionInterface
{
    /**
     * The value a chip of the key gets when the suggestion is picked, trimmed
     *
     * @return string
     */
    public function getValue(): string;

    /**
     * What the list shows for the value, translated where it is wording; the value itself when it has no other name
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * A short note shown after the label (the kind of actor, the kind of record, the code behind a label), or empty
     *
     * @return string
     */
    public function getHint(): string;

    /**
     * How many recent activities carry the value, or null when the list is not counted
     *
     * @return int|null
     */
    public function getCount(): ?int;

    /**
     * A copy with this value, and the label the list shows for it
     *
     * @param string $value Trimmed, then 1 to SearchCriterionInterface::MAX_VALUE_LENGTH characters
     * @param string|null $label Null or blank to show the value itself
     * @return SuggestionInterface
     * @throws \InvalidArgumentException When the trimmed value is empty or too long for a chip
     */
    public function withValue(string $value, ?string $label = null): SuggestionInterface;

    /**
     * A copy with this hint
     *
     * @param string $hint
     * @return SuggestionInterface
     */
    public function withHint(string $hint): SuggestionInterface;

    /**
     * A copy with this count
     *
     * @param int|null $count 0 or more, or null when not counted
     * @return SuggestionInterface
     * @throws \InvalidArgumentException When the count is negative
     */
    public function withCount(?int $count): SuggestionInterface;
}
