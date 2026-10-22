<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Grid;

use Hryvinskyi\ActivityLog\Api\Data\Search\EventDetailInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\FieldChangeInterface;

/**
 * The grid's Field, Before → After and Scope columns of one activity, from the details its module gives
 *
 * The text columns read as the admin reads them in an exported file: the changed field's label (or the labels of all
 * changed fields), "before → after" of each changed field with "(empty)" for no value, and the scopes the values
 * apply to. The rendered grid draws the first changed field's values from KIND, BEFORE and AFTER. Only changed fields
 * count: a snapshot also records fields that kept their value. The fields the actor asked for are preferred: while an
 * activity changed any, those set automatically are left out of the columns; an activity that only changed fields
 * automatically shows those. An activity without details has none of these.
 */
class ChangeColumns
{
    public const FIELD = 'change_field';
    public const FIELD_CODE = 'change_field_code';
    public const FIELD_COUNT = 'change_field_count';
    public const VALUES = 'change_values';
    public const KIND = 'change_kind';
    public const BEFORE = 'change_before';
    public const AFTER = 'change_after';
    public const SCOPE = 'change_scope';

    /**
     * The columns of an activity, every one present, null when it has nothing to show
     *
     * @param EventDetailInterface|null $detail
     * @return array<string, string|int|null>
     */
    public function values(?EventDetailInterface $detail): array
    {
        $values = [
            self::FIELD => $detail?->getWhat(),
            self::FIELD_CODE => null,
            self::FIELD_COUNT => $detail?->getFieldCount() ?? 0,
            self::VALUES => null,
            self::KIND => null,
            self::BEFORE => null,
            self::AFTER => null,
            self::SCOPE => null,
        ];
        $changed = $detail === null ? [] : array_values(array_filter(
            $detail->getFields(),
            static fn (FieldChangeInterface $field): bool => $field->isChanged()
        ));
        if ($detail === null || $changed === []) {
            return $values;
        }

        $asked = array_values(array_filter(
            $changed,
            static fn (FieldChangeInterface $field): bool => !$field->isAutomatic()
        ));
        $askedCount = $detail->getFieldCount() - $detail->getAutomaticCount();
        [$changed, $count] = $asked !== [] || $askedCount > 0
            ? [$asked, max(count($asked), $askedCount)]
            : [$changed, max(count($changed), $detail->getFieldCount())];
        if ($changed === []) {
            return $values;
        }

        $first = $changed[0];

        return [
            self::FIELD => $this->fieldText($changed, $count),
            self::FIELD_CODE => $count === 1 && $first->getName() !== $first->getLabel() ? $first->getName() : null,
            self::FIELD_COUNT => $count,
            self::VALUES => $this->valuesText($changed),
            self::KIND => $first->getKind()->value,
            self::BEFORE => $first->getBefore(),
            self::AFTER => $first->getAfter(),
            self::SCOPE => $this->scopeText($changed),
        ];
    }

    /**
     * The changed field's label, or the labels of the changed fields shown and how many more there are
     *
     * @param non-empty-list<FieldChangeInterface> $changed
     * @param int $count
     * @return string
     */
    private function fieldText(array $changed, int $count): string
    {
        $labels = implode(
            ', ',
            array_map(static fn (FieldChangeInterface $field): string => $field->getLabel(), $changed)
        );
        $more = $count - count($changed);

        return $more > 0 ? (string) __('%1 and %2 more', $labels, $more) : $labels;
    }

    /**
     * "before → after", one line per changed field, each named when there are several
     *
     * @param non-empty-list<FieldChangeInterface> $changed
     * @return string
     */
    private function valuesText(array $changed): string
    {
        $lines = [];
        foreach ($changed as $field) {
            $line = (string) __('%1 → %2', $this->shown($field->getBefore()), $this->shown($field->getAfter()));
            $lines[] = count($changed) > 1 ? (string) __('%1: %2', $field->getLabel(), $line) : $line;
        }

        return implode("\n", $lines);
    }

    /**
     * The scopes the changed values apply to, or null when none is named
     *
     * @param non-empty-list<FieldChangeInterface> $changed
     * @return string|null
     */
    private function scopeText(array $changed): ?string
    {
        $scopes = [];
        foreach ($changed as $field) {
            $scope = $field->getScope();
            if ($scope !== null && $scope !== '') {
                $scopes[$scope] = $scope;
            }
        }

        return $scopes === [] ? null : implode(', ', $scopes);
    }

    /**
     * A value as the text shows it
     *
     * @param string|null $value
     * @return string
     */
    private function shown(?string $value): string
    {
        return $value === null || $value === '' ? (string) __('(empty)') : $value;
    }
}
