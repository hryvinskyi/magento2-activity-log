<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchKeyControl;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchKeyGroup;

/**
 * How a search key is presented: its name, its hint, its control in the filters panel, the suggestion group its values
 * appear in, and whether it names fields
 *
 * One per key, registered by the module that serves the key in the "keyDescriptions" argument of
 * Hryvinskyi\ActivityLog\Model\Search\KeyDescriptionPool. Label and hint are translated and name nothing outside the
 * registering module.
 *
 * @api
 */
interface SearchKeyDescriptionInterface
{
    /**
     * The key described, such as "by"
     *
     * @return string
     */
    public function getKey(): string;

    /**
     * Name of the key, translated
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * What a value of the key may be, translated
     *
     * @return string
     */
    public function getHint(): string;

    /**
     * How the filters panel asks for the value
     *
     * @return SearchKeyControl
     */
    public function getControl(): SearchKeyControl;

    /**
     * The values a select offers, with translated labels; empty for a text control
     *
     * @return list<array{value: string, label: string}>
     */
    public function getOptions(): array;

    /**
     * Whether the filters panel has a control for the key
     *
     * A key reached another way stays out of the panel: a record key whose value is typed into a select's companion
     * text box, or plain text, which is what the search bar is for.
     *
     * @return bool
     */
    public function isInPanel(): bool;

    /**
     * The key each option of a select stands for, by option value, for the panel's companion text box
     *
     * When the companion box holds a value, the panel searches it under the key the chosen option names ("an id of
     * this kind of record"); an option naming no key searches it as plain text. Empty without a companion box.
     *
     * @return array<string, string>
     */
    public function getOptionTargets(): array;

    /**
     * Name of the text box the filters panel shows beside the select, translated; empty for none
     *
     * @return string
     */
    public function getCompanionLabel(): string;

    /**
     * What the companion text box takes, translated; shown inside the box
     *
     * @return string
     */
    public function getCompanionHint(): string;

    /**
     * Position in the filters panel, lowest first
     *
     * @return int
     */
    public function getSortOrder(): int;

    /**
     * The group of the search bar's suggestions the key's values are offered in
     *
     * @return SearchKeyGroup
     */
    public function getGroup(): SearchKeyGroup;

    /**
     * Whether the key's value names fields
     *
     * A result's field rows matching such a value are highlighted: a row whose code contains the value, or whose label
     * is the value, ignoring case.
     *
     * @return bool
     */
    public function matchesFieldNames(): bool;
}
