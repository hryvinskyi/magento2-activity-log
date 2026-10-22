<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Ui\DataProvider;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCriterionInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCursorInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchRequestInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\MillisecondClockInterface;
use Hryvinskyi\ActivityLog\Model\Data\Search\SearchCriterion;
use Hryvinskyi\ActivityLog\Model\Data\Search\SearchCursor;
use Hryvinskyi\ActivityLog\Model\Data\Search\SearchRequest;
use Hryvinskyi\ActivityLog\Model\Search\ConditionPool;
use Hryvinskyi\ActivityLog\Model\Search\CursorCodec;
use Hryvinskyi\ActivityLog\Model\Search\InvalidSearchRequestException;
use Hryvinskyi\ActivityLog\Model\Search\RecentDays;
use Hryvinskyi\ActivityLog\Model\Search\RequestParser;
use Hryvinskyi\ActivityLog\Ui\DataProvider\ActivitySearchParams;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Url\DecoderInterface;
use Magento\Framework\Url\Encoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The grid's search parameter becomes the filter value and back into the same request
 */
class ActivitySearchParamsTest extends TestCase
{
    private const NOW_MS = 1_792_500_000_123;
    private const WEEK_START_MS = 1_791_932_400_000;

    public function testTheGridShowsTheSearchItIsGivenWithoutPagePositionOrSize(): void
    {
        $params = $this->params();

        $value = $params->filterValue([
            'from' => '1000',
            'to' => '2000',
            'c' => ['by:jane', 'channel:entity'],
            'changes' => '0',
            'cursor' => 'not even a cursor',
            'limit' => '9999',
        ]);
        $request = $params->request($value);

        self::assertSame([1000, 2000, false, null], [
            $request->getFromMs(),
            $request->getToMs(),
            $request->isChangesOnly(),
            $request->getCursor(),
        ]);
        self::assertSame(
            [['by', 'jane'], ['channel', 'entity']],
            array_map(static fn ($c): array => [$c->getKey(), $c->getValue()], $request->getCriteria())
        );
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function absent(): array
    {
        return ['no parameter' => [null], 'an empty parameter' => ['']];
    }

    #[DataProvider('absent')]
    public function testWithoutASearchTheGridShowsTheChangesOfTheLastSevenDays(mixed $param): void
    {
        $params = $this->params();

        $request = $params->request($params->filterValue($param));

        self::assertSame([self::WEEK_START_MS, self::NOW_MS, true, []], [
            $request->getFromMs(),
            $request->getToMs(),
            $request->isChangesOnly(),
            $request->getCriteria(),
        ]);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function malformed(): array
    {
        return [
            'text' => ['from=1&to=2'],
            'no range' => [['c' => ['by:jane']]],
            'a range that ends before it starts' => [['from' => '5', 'to' => '1']],
            'a filter without a value' => [['from' => '1', 'to' => '2', 'c' => ['by:']]],
        ];
    }

    #[DataProvider('malformed')]
    public function testAMalformedSearchIsRefusedRatherThanShownAsAnother(mixed $param): void
    {
        $this->expectException(InvalidSearchRequestException::class);

        $this->params()->filterValue($param);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unreadable(): array
    {
        return ['not JSON' => ['{from'], 'not a set of parameters' => ['"from"'], 'empty' => ['']];
    }

    #[DataProvider('unreadable')]
    public function testAFilterValueThatIsNotASearchIsRefused(string $value): void
    {
        $this->expectException(InvalidSearchRequestException::class);

        $this->params()->request($value);
    }

    /**
     * @return ActivitySearchParams
     */
    private function params(): ActivitySearchParams
    {
        $requests = $this->createMock(SearchRequestInterfaceFactory::class);
        $requests->method('create')->willReturnCallback(static fn (): SearchRequest => new SearchRequest());
        $criteria = $this->createMock(SearchCriterionInterfaceFactory::class);
        $criteria->method('create')->willReturnCallback(static fn (): SearchCriterion => new SearchCriterion());
        $cursors = $this->createMock(SearchCursorInterfaceFactory::class);
        $cursors->method('create')->willReturnCallback(static fn (): SearchCursor => new SearchCursor());

        $recentDays = $this->createMock(RecentDays::class);
        $recentDays->method('startMs')->willReturnMap([[7, self::NOW_MS, self::WEEK_START_MS]]);
        $clock = $this->createMock(MillisecondClockInterface::class);
        $clock->method('nowMs')->willReturn(self::NOW_MS);

        return new ActivitySearchParams(
            new RequestParser(
                $requests,
                $criteria,
                new CursorCodec($cursors, new Encoder(), $this->createMock(DecoderInterface::class)),
                new ConditionPool()
            ),
            $recentDays,
            $clock,
            new Json()
        );
    }
}
