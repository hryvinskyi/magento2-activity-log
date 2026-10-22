<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Data\Search;

/**
 * One field of a recorded change, as a search result shows it: what it is called, where, and before → after
 *
 * Values travel to the browser capped at VALUE_LIMIT characters each. A longer value is cut on a character boundary,
 * the row says it was truncated and keeps each side's full length, and it is shown as long text whatever kind it was
 * given (a cut JSON array no longer decodes); a masked value stays masked. The activity's detail page shows the whole
 * value.
 *
 * Create one through FieldChangeInterfaceFactory and fill it with the with…() methods, each of which returns a copy;
 * a row straight from the factory has no name, which withField() must give it.
 *
 * @api
 */
interface FieldChangeInterface
{
    /**
     * Characters of a value sent to the browser
     */
    public const VALUE_LIMIT = 4000;

    /**
     * Code of the field as recorded (an attribute code, a configuration path)
     *
     * @return string
     */
    public function getName(): string;

    /**
     * The field's name as the admin sees it, or its code when it has no other
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * Where the value applies (a store view, a website, the default scope), as the admin sees it, or null
     *
     * @return string|null
     */
    public function getScope(): ?string;

    /**
     * The value before the change, at most VALUE_LIMIT characters, or null when there was none
     *
     * @return string|null
     */
    public function getBefore(): ?string;

    /**
     * The value after the change, at most VALUE_LIMIT characters, or null when there is none
     *
     * @return string|null
     */
    public function getAfter(): ?string;

    /**
     * Full length in characters of the value before the change, 0 when there was none
     *
     * @return int
     */
    public function getBeforeLength(): int;

    /**
     * Full length in characters of the value after the change, 0 when there is none
     *
     * @return int
     */
    public function getAfterLength(): int;

    /**
     * Whether either value was cut to VALUE_LIMIT characters
     *
     * @return bool
     */
    public function isTruncated(): bool;

    /**
     * How the values are shown: the kind given, or long text when a value that is not masked was cut
     *
     * @return ValueKind
     */
    public function getKind(): ValueKind;

    /**
     * Whether the values are masked secrets
     *
     * @return bool
     */
    public function isMasked(): bool;

    /**
     * Whether the value changed; a snapshot also records fields that kept their value
     *
     * @return bool
     */
    public function isChanged(): bool;

    /**
     * Whether the change was set automatically
     *
     * The system made it while carrying out what the actor asked, without the actor asking for it. Only a changed
     * field is ever set automatically.
     *
     * @return bool
     */
    public function isAutomatic(): bool;

    /**
     * A copy naming the field
     *
     * @param string $name Code of the field, not empty
     * @param string|null $label The admin's name for it; null or empty uses the code
     * @return FieldChangeInterface
     * @throws \InvalidArgumentException When the code is empty
     */
    public function withField(string $name, ?string $label = null): FieldChangeInterface;

    /**
     * A copy with the scope the value applies to
     *
     * @param string|null $scope
     * @return FieldChangeInterface
     */
    public function withScope(?string $scope): FieldChangeInterface;

    /**
     * A copy with the values, each cut to VALUE_LIMIT characters
     *
     * @param string|null $before
     * @param string|null $after
     * @return FieldChangeInterface
     */
    public function withValues(?string $before, ?string $after): FieldChangeInterface;

    /**
     * A copy shown as this kind; a cut value is still shown as long text unless masked
     *
     * @param ValueKind $kind
     * @return FieldChangeInterface
     */
    public function withKind(ValueKind $kind): FieldChangeInterface;

    /**
     * A copy saying whether the value changed
     *
     * @param bool $changed
     * @return FieldChangeInterface
     */
    public function withChanged(bool $changed): FieldChangeInterface;

    /**
     * A copy saying whether the change was set automatically
     *
     * @param bool $automatic
     * @return FieldChangeInterface
     */
    public function withAutomatic(bool $automatic): FieldChangeInterface;
}
