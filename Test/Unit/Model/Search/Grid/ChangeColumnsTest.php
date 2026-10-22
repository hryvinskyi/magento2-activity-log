<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Search\Grid;

use Hryvinskyi\ActivityLog\Api\Data\Search\EventDetailInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\FieldChangeInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\ValueKind;
use Hryvinskyi\ActivityLog\Api\Data\Search\Verb;
use Hryvinskyi\ActivityLog\Model\Data\Search\EventDetail;
use Hryvinskyi\ActivityLog\Model\Data\Search\FieldChange;
use Hryvinskyi\ActivityLog\Model\Data\Search\LinkPolicy;
use Hryvinskyi\ActivityLog\Model\Search\Grid\ChangeColumns;
use PHPUnit\Framework\TestCase;

/**
 * The grid's change columns read as the admin reads them, from the changed fields only
 */
class ChangeColumnsTest extends TestCase
{
    public function testOneChangedFieldIsNamedWithItsCodeValuesAndScope(): void
    {
        $detail = $this->detail(1, [
            (new FieldChange())->withField('price', 'Price')->withScope('Default Store View')
                ->withValues('34.00', null),
            (new FieldChange())->withField('name', 'Name')->withValues('Shirt', 'Shirt')->withChanged(false),
        ]);

        self::assertSame([
            ChangeColumns::FIELD => 'Price',
            ChangeColumns::FIELD_CODE => 'price',
            ChangeColumns::FIELD_COUNT => 1,
            ChangeColumns::VALUES => '34.00 → (empty)',
            ChangeColumns::KIND => 'short',
            ChangeColumns::BEFORE => '34.00',
            ChangeColumns::AFTER => null,
            ChangeColumns::SCOPE => 'Default Store View',
        ], (new ChangeColumns())->values($detail));
    }

    public function testSeveralChangedFieldsAreListedEachWithItsValues(): void
    {
        $detail = $this->detail(4, [
            (new FieldChange())->withField('price', 'Price')->withScope('Main Website')->withValues('1', '2'),
            (new FieldChange())->withField('api_secret')->withKind(ValueKind::Masked)
                ->withScope('Main Website')->withValues('••••••', '••••••'),
            (new FieldChange())->withField('status', 'Status')->withScope('Default Config')->withValues('', '1'),
        ], 1);

        $values = (new ChangeColumns())->values($detail);

        self::assertSame('Price, api_secret, Status and 1 more', $values[ChangeColumns::FIELD]);
        self::assertNull($values[ChangeColumns::FIELD_CODE], 'Codes are shown for one field only');
        self::assertSame(4, $values[ChangeColumns::FIELD_COUNT]);
        self::assertSame(
            "Price: 1 → 2\napi_secret: •••••• → ••••••\nStatus: (empty) → 1",
            $values[ChangeColumns::VALUES]
        );
        self::assertSame(['short', '1', '2'], [
            $values[ChangeColumns::KIND],
            $values[ChangeColumns::BEFORE],
            $values[ChangeColumns::AFTER],
        ], 'The rendered cell draws the first changed field');
        self::assertSame('Main Website, Default Config', $values[ChangeColumns::SCOPE]);
    }

    public function testTheFieldsTheActorAskedForArePreferredOverThoseSetAutomatically(): void
    {
        $label = static fn (string $code): FieldChangeInterface => (new FieldChange())->withField($code)
            ->withScope('All Store Views')->withValues('Joust Duffle Bag', null)->withAutomatic(true);
        $price = (new FieldChange())->withField('price', 'Price')->withScope('Main Website')->withValues('1', '2');

        $mixed = (new ChangeColumns())->values(
            $this->detail(3, [$label('image_label'), $price, $label('thumbnail_label')])->withAutomaticCount(2)
        );

        self::assertSame(['Price', 'price', 1, '1 → 2', '1', '2', 'Main Website'], [
            $mixed[ChangeColumns::FIELD],
            $mixed[ChangeColumns::FIELD_CODE],
            $mixed[ChangeColumns::FIELD_COUNT],
            $mixed[ChangeColumns::VALUES],
            $mixed[ChangeColumns::BEFORE],
            $mixed[ChangeColumns::AFTER],
            $mixed[ChangeColumns::SCOPE],
        ], 'The fields set automatically are left out while one was asked for');

        $automatic = (new ChangeColumns())->values(
            $this->detail(2, [$label('image_label'), $label('thumbnail_label')])->withAutomaticCount(2)
        );

        self::assertSame(['image_label, thumbnail_label', 2, 'Joust Duffle Bag'], [
            $automatic[ChangeColumns::FIELD],
            $automatic[ChangeColumns::FIELD_COUNT],
            $automatic[ChangeColumns::BEFORE],
        ], 'With nothing asked for, the fields set automatically are shown');
    }

    public function testAFieldWhoseLabelIsItsCodeHasNoSeparateCode(): void
    {
        $values = (new ChangeColumns())->values(
            $this->detail(1, [(new FieldChange())->withField('carriers/flatrate/active')->withValues('0', '1')])
        );

        self::assertSame(['carriers/flatrate/active', null], [
            $values[ChangeColumns::FIELD],
            $values[ChangeColumns::FIELD_CODE],
        ]);
    }

    public function testAnActivityWithoutChangedFieldsKeepsWhatItsDetailSays(): void
    {
        $columns = new ChangeColumns();
        $created = $this->detail(0, [], 0, 'Shirt');

        self::assertSame([
            ChangeColumns::FIELD => 'Shirt',
            ChangeColumns::FIELD_CODE => null,
            ChangeColumns::FIELD_COUNT => 0,
            ChangeColumns::VALUES => null,
            ChangeColumns::KIND => null,
            ChangeColumns::BEFORE => null,
            ChangeColumns::AFTER => null,
            ChangeColumns::SCOPE => null,
        ], $columns->values($created));
        self::assertSame([null, 0], [
            $columns->values(null)[ChangeColumns::FIELD],
            $columns->values(null)[ChangeColumns::FIELD_COUNT],
        ]);
    }

    /**
     * @param int $fieldCount
     * @param list<FieldChangeInterface> $fields
     * @param int $more
     * @param string|null $what
     * @return EventDetailInterface
     */
    private function detail(int $fieldCount, array $fields, int $more = 0, ?string $what = null): EventDetailInterface
    {
        return (new EventDetail(new LinkPolicy()))
            ->withVerb(Verb::Changed)
            ->withWhat($what, $fieldCount)
            ->withFields($fields, $more);
    }
}
