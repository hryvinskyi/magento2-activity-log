<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Search;

use Hryvinskyi\ActivityLog\Api\Search\SearchConditionInterface;
use Hryvinskyi\ActivityLog\Model\Data\Search\SearchCriterion;
use Hryvinskyi\ActivityLog\Model\Data\Search\SearchRequest;
use Hryvinskyi\ActivityLog\Model\Search\ConditionPool;
use Hryvinskyi\ActivityLog\Model\Search\CriteriaResolver;
use Hryvinskyi\ActivityLog\Model\Search\Journal\JournalConditionInterface;
use Hryvinskyi\ActivityLog\Model\Search\Query\ResolvedCriterion;
use Hryvinskyi\ActivityLog\Model\Search\Query\ResolvedCriterionFactory;
use Hryvinskyi\ActivityLog\Model\Search\Query\TextWords;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

/**
 * Filters become SQL alternatives: unknown keys fall back to text, contributions to one key are alternatives, and
 * plain text is one required group per word
 */
class CriteriaResolverTest extends TestCase
{
    public function testAKeyNobodyServesIsSearchedAsTextAsTyped(): void
    {
        $text = $this->journalCondition('text', residual: true);

        $resolved = $this->resolver(new ConditionPool([], ['text' => $text]))
            ->resolve($this->request(['colour' => 'deep red']));

        self::assertSame(
            ['((t.words = colour:))', '((t.words = deep))', '((t.words = red))'],
            array_map(static fn (ResolvedCriterion $group): string => $group->render('t'), $resolved)
        );
        self::assertTrue($resolved[0]->isResidual());
    }

    public function testEachWordIsOneGroupAskingTheJournalAndEveryModuleAboutThatWordAlone(): void
    {
        $asked = [];
        $matcher = $this->createMock(SearchConditionInterface::class);
        $matcher->method('getKey')->willReturn('text');
        $matcher->method('isResidual')->willReturn(true);
        $matcher->method('activityIds')->willReturnCallback(function (string $word) use (&$asked): Select {
            $asked[] = $word;
            $select = $this->createMock(Select::class);
            $select->method('assemble')->willReturn('SELECT activity_id FROM fields WHERE word = ' . $word);

            return $select;
        });
        $pool = new ConditionPool(['matcher' => $matcher], ['text' => $this->journalCondition('text', true)]);

        $resolved = $this->resolver($pool)->resolve($this->request(['text' => 'MB01 price']));

        self::assertSame(['MB01', 'price'], $asked, 'A module is asked about single words');
        self::assertSame(
            [
                '((t.words = MB01) OR (t.entity_id IN (SELECT activity_id FROM fields WHERE word = MB01)))',
                '((t.words = price) OR (t.entity_id IN (SELECT activity_id FROM fields WHERE word = price)))',
            ],
            array_map(static fn (ResolvedCriterion $group): string => $group->render('t'), $resolved),
            'Each word may be found by the journal or by a module; every word must be found'
        );
    }

    public function testALongFilterIsSearchedForItsFirstEightDistinctWords(): void
    {
        $pool = new ConditionPool([], ['text' => $this->journalCondition('text', true)]);

        $resolved = $this->resolver($pool)->resolve(
            $this->request(['text' => trim(str_repeat('a b A c d e f g h i j k ', 10))])
        );

        self::assertSame(
            ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'],
            array_map(static fn (ResolvedCriterion $group): string => substr($group->render('t'), 12, -2), $resolved)
        );
    }

    public function testPlainTextWithNoWordLeftMatchesNothing(): void
    {
        $pool = new ConditionPool([], ['text' => $this->journalCondition('text', true)]);

        $resolved = $this->resolver($pool)->resolve($this->request(['text' => '"" ""']));

        self::assertCount(1, $resolved);
        self::assertSame('1 = 0', $resolved[0]->render('t'));
    }

    public function testAKeyServedByTheJournalAndAModuleMatchesEither(): void
    {
        $pool = new ConditionPool(
            ['scope' => $this->contributedCondition('store', 'SELECT activity_id FROM scopes', residual: false)],
            ['store' => $this->journalCondition('store', residual: false)]
        );

        $resolved = $this->resolver($pool)->resolve($this->request(['store' => 'main']));

        self::assertSame(
            '((m.store = main) OR (m.entity_id IN (SELECT activity_id FROM scopes)))',
            $resolved[0]->render('m')
        );
        self::assertFalse($resolved[0]->isResidual());
    }

    public function testOneResidualAlternativeMakesTheWholeFilterResidual(): void
    {
        $pool = new ConditionPool(
            ['matcher' => $this->contributedCondition('text', 'SELECT activity_id FROM words', residual: true)],
            ['text' => $this->journalCondition('text', residual: false)]
        );

        self::assertTrue($this->resolver($pool)->resolve($this->request(['text' => 'x']))[0]->isResidual());
    }

    public function testAContributionThatMatchesNothingLeavesTheFilterMatchingNothing(): void
    {
        $pool = new ConditionPool(['record' => $this->contributedCondition('record', null, residual: false)]);

        $resolved = $this->resolver($pool)->resolve($this->request(['record' => 'no such thing']));

        self::assertSame('1 = 0', $resolved[0]->render('m'));
    }

    public function testFiltersKeepTheirOrder(): void
    {
        $pool = new ConditionPool([], [
            'by' => $this->journalCondition('by', residual: false),
            'via' => $this->journalCondition('via', residual: true),
        ]);

        $resolved = $this->resolver($pool)->resolve($this->request(['via' => 'desk', 'by' => 'jane']));

        self::assertSame(
            ['((m.via = desk))', '((m.by = jane))'],
            [$resolved[0]->render('m'), $resolved[1]->render('m')]
        );
    }

    /**
     * @param array<string, string> $chips
     * @return SearchRequest
     */
    private function request(array $chips): SearchRequest
    {
        $criteria = [];
        foreach ($chips as $key => $value) {
            $criteria[] = (new SearchCriterion())->withTerm($key, $value);
        }

        $request = (new SearchRequest())->withCriteria($criteria);
        self::assertInstanceOf(SearchRequest::class, $request);

        return $request;
    }

    /**
     * A resolver whose factory builds real resolved filters
     *
     * @param ConditionPool $pool
     * @return CriteriaResolver
     */
    private function resolver(ConditionPool $pool): CriteriaResolver
    {
        $factory = $this->createMock(ResolvedCriterionFactory::class);
        $factory->method('create')->willReturnCallback(
            static function (array $data): ResolvedCriterion {
                self::assertIsBool($data['residual'] ?? null);
                self::assertIsArray($data['alternatives'] ?? null);
                /** @var list<\Closure(string,bool):?string> $alternatives */
                $alternatives = $data['alternatives'];

                return new ResolvedCriterion($data['residual'], $alternatives);
            }
        );

        return new CriteriaResolver($pool, $factory, $this->createMock(ResourceConnection::class), new TextWords());
    }

    /**
     * A journal condition rendering "<alias>.<key> = <value>"
     *
     * @param string $key
     * @param bool $residual
     * @return JournalConditionInterface
     */
    private function journalCondition(string $key, bool $residual): JournalConditionInterface
    {
        $condition = $this->createMock(JournalConditionInterface::class);
        $condition->method('getKey')->willReturn($key);
        $condition->method('isResidual')->willReturn($residual);
        $condition->method('condition')->willReturnCallback(
            static fn (string $value, string $alias): string => $alias . '.' . ($key === 'text' ? 'words' : $key)
                . ' = ' . $value
        );

        return $condition;
    }

    /**
     * A contributed condition answering with a fixed sub-select, or with none
     *
     * @param string $key
     * @param string|null $sql
     * @param bool $residual
     * @return SearchConditionInterface
     */
    private function contributedCondition(string $key, ?string $sql, bool $residual): SearchConditionInterface
    {
        $select = null;
        if ($sql !== null) {
            $select = $this->createMock(Select::class);
            $select->method('assemble')->willReturn($sql);
        }

        $condition = $this->createMock(SearchConditionInterface::class);
        $condition->method('getKey')->willReturn($key);
        $condition->method('isResidual')->willReturn($residual);
        $condition->method('activityIds')->willReturn($select);

        return $condition;
    }
}
