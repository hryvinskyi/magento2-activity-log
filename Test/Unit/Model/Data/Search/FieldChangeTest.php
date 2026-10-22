<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Data\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\FieldChangeInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\ValueKind;
use Hryvinskyi\ActivityLog\Model\Data\Search\FieldChange;
use PHPUnit\Framework\TestCase;

/**
 * A field row carries values to the browser capped at 4,000 characters, and says so
 */
class FieldChangeTest extends TestCase
{
    public function testShortValuesTravelWholeAsGiven(): void
    {
        $row = (new FieldChange())->withField('price', 'Price')->withScope('Default Store View')
            ->withValues('34.00', '39.00');

        self::assertSame(['34.00', '39.00'], [$row->getBefore(), $row->getAfter()]);
        self::assertSame([5, 5], [$row->getBeforeLength(), $row->getAfterLength()]);
        self::assertFalse($row->isTruncated());
        self::assertSame(ValueKind::Short, $row->getKind());
        self::assertSame(
            ['price', 'Price', 'Default Store View'],
            [$row->getName(), $row->getLabel(), $row->getScope()]
        );
    }

    public function testAValueOverTheLimitIsCutOnACharacterBoundaryAndKeepsItsFullLength(): void
    {
        $long = str_repeat('é', FieldChangeInterface::VALUE_LIMIT + 1000);

        $row = (new FieldChange())->withField('description')->withValues(null, $long);

        self::assertNull($row->getBefore());
        self::assertSame(str_repeat('é', FieldChangeInterface::VALUE_LIMIT), $row->getAfter());
        self::assertSame(
            [0, FieldChangeInterface::VALUE_LIMIT + 1000],
            [$row->getBeforeLength(), $row->getAfterLength()]
        );
        self::assertTrue($row->isTruncated());
    }

    public function testAValueOfExactlyTheLimitIsNotCut(): void
    {
        $row = (new FieldChange())->withField('content')
            ->withValues(str_repeat('a', FieldChangeInterface::VALUE_LIMIT), 'b');

        self::assertFalse($row->isTruncated());
        self::assertSame(FieldChangeInterface::VALUE_LIMIT, strlen((string) $row->getBefore()));
    }

    public function testACutValueIsShownAsLongTextWhateverKindItWasGivenInEitherOrder(): void
    {
        $json = '[' . str_repeat('"x",', FieldChangeInterface::VALUE_LIMIT) . '"x"]';
        $field = (new FieldChange())->withField('resources');

        self::assertSame(ValueKind::Long, $field->withKind(ValueKind::Json)->withValues($json, '[]')->getKind());
        self::assertSame(ValueKind::Long, $field->withValues($json, '[]')->withKind(ValueKind::Short)->getKind());
        self::assertSame(ValueKind::Json, $field->withKind(ValueKind::Json)->withValues('[1]', '[2]')->getKind());
    }

    public function testAMaskedValueStaysMasked(): void
    {
        $row = (new FieldChange())->withField('secret')->withKind(ValueKind::Masked)
            ->withValues(str_repeat('•', FieldChangeInterface::VALUE_LIMIT + 1), '••••••');

        self::assertSame(ValueKind::Masked, $row->getKind());
        self::assertTrue($row->isMasked());
    }

    public function testTheLabelFallsBackToTheCode(): void
    {
        self::assertSame('sku', (new FieldChange())->withField('sku', '')->getLabel());
        self::assertSame('sku', (new FieldChange())->withField('sku')->getLabel());
    }

    public function testAFieldWithoutACodeIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new FieldChange())->withField('');
    }

    public function testOnlyAChangedFieldIsEverSetAutomatically(): void
    {
        $field = (new FieldChange())->withField('image_label')->withValues('Bag', null);

        self::assertFalse($field->isAutomatic(), 'Asked for unless said');
        self::assertTrue($field->withAutomatic(true)->isAutomatic());
        self::assertFalse($field->withAutomatic(true)->withChanged(false)->isAutomatic());
    }

    public function testEveryWithLeavesTheOriginalAsItWas(): void
    {
        $original = (new FieldChange())->withField('price')->withValues('1', '2');

        $original->withValues('3', '4')->withKind(ValueKind::Json)->withChanged(false)->withScope('x')
            ->withAutomatic(true);

        self::assertSame(['1', '2', ValueKind::Short, true, null, false], [
            $original->getBefore(),
            $original->getAfter(),
            $original->getKind(),
            $original->isChanged(),
            $original->getScope(),
            $original->isAutomatic(),
        ]);
    }
}
