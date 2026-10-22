<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Search;

use Hryvinskyi\ActivityLog\Model\Data\Search\SearchCriterion;
use Hryvinskyi\ActivityLog\Model\Data\Search\SearchCursor;
use Hryvinskyi\ActivityLog\Model\Data\Search\SearchRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The search request and its filters stay valid through every copy
 */
class SearchRequestTest extends TestCase
{
    public function testARequestFromTheFactoryIsTheFirstPageOfFiftyOverAnEmptyRange(): void
    {
        $request = new SearchRequest();

        self::assertSame([0, 0, [], false, null, 50], [
            $request->getFromMs(),
            $request->getToMs(),
            $request->getCriteria(),
            $request->isChangesOnly(),
            $request->getCursor(),
            $request->getLimit(),
        ]);
    }

    public function testEachCopyCarriesItsChangeAndLeavesTheOriginalAlone(): void
    {
        $original = new SearchRequest();
        $criterion = (new SearchCriterion())->withTerm('by', 'jane');
        $cursor = (new SearchCursor())->withPosition(1_792_500_000_000, 42);

        $copy = $original->withRange(1000, 2000)
            ->withCriteria([$criterion])
            ->withChangesOnly(true)
            ->withCursor($cursor)
            ->withLimit(200);

        self::assertSame([1000, 2000, [$criterion], true, $cursor, 200], [
            $copy->getFromMs(),
            $copy->getToMs(),
            $copy->getCriteria(),
            $copy->isChangesOnly(),
            $copy->getCursor(),
            $copy->getLimit(),
        ]);
        self::assertSame([0, 0, [], false, null, 50], [
            $original->getFromMs(),
            $original->getToMs(),
            $original->getCriteria(),
            $original->isChangesOnly(),
            $original->getCursor(),
            $original->getLimit(),
        ]);
    }

    public function testARangeMayBeASingleMillisecond(): void
    {
        $request = (new SearchRequest())->withRange(5, 5);

        self::assertSame([5, 5], [$request->getFromMs(), $request->getToMs()]);
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function invalidRanges(): array
    {
        return ['ends before it starts' => [2000, 1000], 'negative start' => [-1, 10]];
    }

    #[DataProvider('invalidRanges')]
    public function testRefusesARangeThatIsNotOne(int $fromMs, int $toMs): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SearchRequest())->withRange($fromMs, $toMs);
    }

    public function testTakesTwentyFiltersButNotTwentyOne(): void
    {
        $criterion = (new SearchCriterion())->withTerm('text', 'word');

        $request = (new SearchRequest())->withCriteria(array_fill(0, 20, $criterion));
        self::assertCount(20, $request->getCriteria());

        $this->expectException(\InvalidArgumentException::class);
        (new SearchRequest())->withCriteria(array_fill(0, 21, $criterion));
    }

    public function testRefusesABlankFilter(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SearchRequest())->withCriteria([new SearchCriterion()]);
    }

    public function testRefusesACursorStillAtItsBlankPosition(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SearchRequest())->withCursor(new SearchCursor());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function invalidLimits(): array
    {
        return ['zero' => [0], 'over two hundred' => [201]];
    }

    #[DataProvider('invalidLimits')]
    public function testRefusesAPageSizeOutOfRange(int $limit): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SearchRequest())->withLimit($limit);
    }

    public function testAFilterValueIsTrimmed(): void
    {
        $criterion = (new SearchCriterion())->withTerm('record', "  24-MB01 \n");

        self::assertSame(['record', '24-MB01'], [$criterion->getKey(), $criterion->getValue()]);
    }

    public function testAFilterValueOf255CharactersIsTakenAndCountedInCharacters(): void
    {
        $value = str_repeat('æ', 255);

        self::assertSame($value, (new SearchCriterion())->withTerm('value', $value)->getValue());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidTerms(): array
    {
        return [
            'upper-case key' => ['By', 'jane'],
            'key with a digit' => ['by2', 'jane'],
            'key starting with an underscore' => ['_by', 'jane'],
            'key of 33 characters' => [str_repeat('a', 33), 'jane'],
            'blank value' => ['by', '   '],
            'value of 256 characters' => ['value', str_repeat('x', 256)],
        ];
    }

    #[DataProvider('invalidTerms')]
    public function testRefusesAMalformedFilter(string $key, string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SearchCriterion())->withTerm($key, $value);
    }

    public function testRefusesAPositionWithoutAnActivity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SearchCursor())->withPosition(1000, 0);
    }
}
