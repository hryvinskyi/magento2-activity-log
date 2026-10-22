<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCriterionInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCursorInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchRequestInterfaceFactory;
use Hryvinskyi\ActivityLog\Model\Data\Search\SearchCriterion;
use Hryvinskyi\ActivityLog\Model\Data\Search\SearchCursor;
use Hryvinskyi\ActivityLog\Model\Data\Search\SearchRequest;
use Hryvinskyi\ActivityLog\Model\Search\ConditionPool;
use Hryvinskyi\ActivityLog\Model\Search\CursorCodec;
use Hryvinskyi\ActivityLog\Model\Search\Journal\JournalConditionInterface;
use Magento\Framework\Url\DecoderInterface;
use Magento\Framework\Url\Encoder;
use Hryvinskyi\ActivityLog\Model\Search\InvalidSearchRequestException;
use Hryvinskyi\ActivityLog\Model\Search\RequestParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Query parameters become a request, or a refusal the person can read
 */
class RequestParserTest extends TestCase
{
    public function testReadsEveryPartOfTheRequest(): void
    {
        $codec = $this->codec();
        $cursor = $codec->encode((new SearchCursor())->withPosition(1500, 7));

        $request = $this->parser($codec)->parse([
            'from' => '1000',
            'to' => '2000',
            'c' => ['by:jane', ' record : 24-MB01 ', 'text:a: b'],
            'changes' => '1',
            'cursor' => $cursor,
            'limit' => '20',
        ]);

        self::assertSame([1000, 2000, true, 20], [
            $request->getFromMs(),
            $request->getToMs(),
            $request->isChangesOnly(),
            $request->getLimit(),
        ]);
        self::assertSame(
            [['by', 'jane'], ['record', '24-MB01'], ['text', 'a: b']],
            array_map(static fn ($c): array => [$c->getKey(), $c->getValue()], $request->getCriteria()),
            'Split at the first colon; key and value trimmed'
        );
        $position = $request->getCursor();
        self::assertSame([1500, 7], [$position?->getOccurredAtMs(), $position?->getActivityId()]);
    }

    public function testDefaultsToTheFirstPageOfFiftyWithoutFilters(): void
    {
        $request = $this->parser()->parse(['from' => '0', 'to' => '10']);

        self::assertSame([[], false, null, 50], [
            $request->getCriteria(),
            $request->isChangesOnly(),
            $request->getCursor(),
            $request->getLimit(),
        ]);
    }

    public function testTakesTwentyFiltersOfWhichEightArePlainText(): void
    {
        $request = $this->parser()->parse([
            'from' => '0',
            'to' => '10',
            'c' => [...array_fill(0, 8, 'text:x'), ...array_fill(0, 12, 'by:x')],
        ]);

        self::assertCount(20, $request->getCriteria());
    }

    /**
     * @return array<string, array{list<string>}>
     */
    public static function tooMuchText(): array
    {
        return [
            'nine text filters' => [array_fill(0, 9, 'text:x')],
            'eight text filters and one key nothing serves' => [[...array_fill(0, 8, 'text:x'), 'colour:red']],
            'nine keys nothing serves' => [array_fill(0, 9, 'colour:red')],
        ];
    }

    /**
     * @param list<string> $chips
     */
    #[DataProvider('tooMuchText')]
    public function testRefusesANinthPlainTextFilterCountingKeysSearchedAsText(array $chips): void
    {
        try {
            $this->parser()->parse(['from' => '0', 'to' => '10', 'c' => [...$chips, 'by:jane']]);
            self::fail('A ninth plain-text filter was accepted.');
        } catch (InvalidSearchRequestException $refused) {
            self::assertStringContainsString('8 text filters', $refused->getReason());
        }
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function malformed(): array
    {
        $range = ['from' => '1000', 'to' => '2000'];

        return [
            'no range' => [[]],
            'no end' => [['from' => '1000']],
            'a range that is not a number' => [['from' => '1e3', 'to' => '2000']],
            'a negative start' => [['from' => '-1', 'to' => '2000']],
            'an end before the start' => [['from' => '2000', 'to' => '1000']],
            'twenty-one filters' => [$range + ['c' => array_fill(0, 21, 'text:x')]],
            'filters that are not a list' => [$range + ['c' => 'by:jane']],
            'a filter without a colon' => [$range + ['c' => ['jane']]],
            'a filter with an empty value' => [$range + ['c' => ['by:  ']]],
            'a filter with a malformed key' => [$range + ['c' => ['By:jane']]],
            'a filter that is not text' => [$range + ['c' => [['by' => 'jane']]]],
            'a filter value of 256 characters' => [$range + ['c' => ['value:' . str_repeat('x', 256)]]],
            'changes neither on nor off' => [$range + ['changes' => 'yes']],
            'a page size of zero' => [$range + ['limit' => '0']],
            'a page size over two hundred' => [$range + ['limit' => '201']],
            'a page size that is not a number' => [$range + ['limit' => 'all']],
            'a garbled cursor' => [$range + ['cursor' => 'not-a-cursor']],
            'a cursor that is not text' => [$range + ['cursor' => ['x']]],
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    #[DataProvider('malformed')]
    public function testRefusesMalformedParametersWithAReason(array $params): void
    {
        try {
            $this->parser()->parse($params);
            self::fail('A malformed request was accepted.');
        } catch (InvalidSearchRequestException $refused) {
            self::assertNotSame('', $refused->getReason());
        }
    }

    public function testRefusesAnAlteredCursor(): void
    {
        $codec = $this->codec();
        $token = $codec->encode((new SearchCursor())->withPosition(1500, 7));
        $altered = substr($token, 0, -1) . (str_ends_with($token, 'A') ? 'B' : 'A');

        $this->expectException(InvalidSearchRequestException::class);
        $this->parser($codec)->parse(['from' => '0', 'to' => '10', 'cursor' => $altered]);
    }

    /**
     * A parser over factories that hand out blank value objects
     *
     * @param CursorCodec|null $codec
     * @return RequestParser
     */
    private function parser(?CursorCodec $codec = null): RequestParser
    {
        $requests = $this->createMock(SearchRequestInterfaceFactory::class);
        $requests->method('create')->willReturnCallback(static fn (): SearchRequest => new SearchRequest());
        $criteria = $this->createMock(SearchCriterionInterfaceFactory::class);
        $criteria->method('create')->willReturnCallback(static fn (): SearchCriterion => new SearchCriterion());

        return new RequestParser($requests, $criteria, $codec ?? $this->codec(), $this->conditions());
    }

    /**
     * Conditions serving "by", "record", "value" and "text"; any other key is searched as text
     *
     * @return ConditionPool
     */
    private function conditions(): ConditionPool
    {
        $conditions = [];
        foreach (['by', 'record', 'value', 'text'] as $key) {
            $condition = $this->createMock(JournalConditionInterface::class);
            $condition->method('getKey')->willReturn($key);
            $conditions[$key] = $condition;
        }

        return new ConditionPool([], $conditions);
    }

    /**
     * @return CursorCodec
     */
    private function codec(): CursorCodec
    {
        $cursors = $this->createMock(SearchCursorInterfaceFactory::class);
        $cursors->method('create')->willReturnCallback(static fn (): SearchCursor => new SearchCursor());

        $decoder = $this->createMock(DecoderInterface::class);
        $decoder->method('decode')->willReturnCallback(
            static fn (string $token): string => (string) base64_decode(strtr($token, '-_~', '+/='))
        );

        return new CursorCodec($cursors, new Encoder(), $decoder);
    }
}
