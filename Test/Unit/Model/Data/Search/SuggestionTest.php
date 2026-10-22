<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Data\Search;

use Hryvinskyi\ActivityLog\Model\Data\Search\Suggestion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A suggestion holds a value a chip can take, the label shown for it, a hint and an optional count
 */
class SuggestionTest extends TestCase
{
    public function testTheValueIsTrimmedAndShownAsItselfWithoutALabel(): void
    {
        $suggestion = (new Suggestion())->withValue('  Jane Doe ');

        self::assertSame(['Jane Doe', 'Jane Doe', '', null], [
            $suggestion->getValue(),
            $suggestion->getLabel(),
            $suggestion->getHint(),
            $suggestion->getCount(),
        ]);
    }

    public function testALabelHintAndCountAreKeptEachOnACopy(): void
    {
        $blank = new Suggestion();
        $named = $blank->withValue('state', ' Turned On ');
        $hinted = $named->withHint(' state ');
        $counted = $hinted->withCount(12);

        self::assertSame('', $blank->getValue());
        self::assertSame('', $named->getHint());
        self::assertNull($hinted->getCount());
        self::assertSame(
            ['state', 'Turned On', 'state', 12],
            [$counted->getValue(), $counted->getLabel(), $counted->getHint(), $counted->getCount()]
        );
        self::assertSame(0, $counted->withCount(0)->getCount());
        self::assertNull($counted->withCount(null)->getCount());
    }

    public function testABlankLabelShowsTheValue(): void
    {
        self::assertSame('size', (new Suggestion())->withValue('size', '   ')->getLabel());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function valuesNoChipTakes(): array
    {
        return [
            'empty' => [''],
            'blank' => ['   '],
            'over 255 characters' => [str_repeat('é', 256)],
        ];
    }

    #[DataProvider('valuesNoChipTakes')]
    public function testAValueNoChipCouldHoldIsRefused(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Suggestion())->withValue($value);
    }

    public function testAValueOf255CharactersIsKept(): void
    {
        self::assertSame(255, mb_strlen((new Suggestion())->withValue(str_repeat('é', 255))->getValue()));
    }

    public function testANegativeCountIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Suggestion())->withValue('x')->withCount(-1);
    }
}
