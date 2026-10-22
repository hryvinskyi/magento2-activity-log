<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model\Search;

use Hryvinskyi\ActivityLog\Api\Search\ActivitySearchInterface;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\SearchFixture;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Plain words: each must appear, as typed, in the action, subject, actor name or correlation id
 *
 * The rows sit in a time window no other test writes to, inside the test's transaction.
 */
#[DbIsolation(true)]
class KeywordSearchTest extends TestCase
{
    private const FROM_MS = 1_900_000_000_000;
    private const CORRELATION = 'op-keyword-search';

    /**
     * @var SearchFixture
     */
    private SearchFixture $fixture;

    /**
     * @var array<string, int>
     */
    private array $ids = [];

    protected function setUp(): void
    {
        $this->fixture = new SearchFixture();
        $this->ids = [
            'duffle' => $this->fixture->insert(self::FROM_MS + 1, [
                'correlation_id' => self::CORRELATION,
                'action' => 'Record saved',
                'subject_label' => 'Record 24-MB01 · Weekend Duffle Bag',
            ]),
            'import' => $this->fixture->insert(self::FROM_MS + 2, [
                'correlation_id' => self::CORRELATION,
                'action' => 'run nightly_import.process',
            ]),
            'actor' => $this->fixture->insert(self::FROM_MS + 3, [
                'correlation_id' => self::CORRELATION,
                'action' => 'Logged in',
                'actor_name' => 'Quentin Zaltz',
            ]),
            'mode' => $this->fixture->insert(self::FROM_MS + 4, [
                'correlation_id' => self::CORRELATION,
                'action' => 'Mode: maintenance switched on',
            ]),
        ];
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function searches(): array
    {
        return [
            'a code with a dash, in any case' => ['24-mb01', ['duffle']],
            'two words in any order' => ['duffle weekend', ['duffle']],
            'part of a word' => ['uffl', ['duffle']],
            'a word with an underscore' => ['nightly_import', ['import']],
            'an underscore is not a wildcard' => ['night_y', []],
            'a percent sign is not a wildcard' => ['%', []],
            'an actor name' => ['zaltz', ['actor']],
            'words in quotes' => ['"Weekend Duffle"', ['duffle']],
            'words found in different columns' => ['zaltz logged', ['actor']],
            'a word and one nobody wrote' => ['duffle nowhere', []],
            'operator characters are matched as typed' => ['+weekend*', []],
            'a trailing dash is matched as typed' => ['weekend-', []],
            'short words count too' => ['24 bag', ['duffle']],
            'only quotes' => ['""', []],
        ];
    }

    /**
     * @param string $text
     * @param list<string> $expected
     */
    #[DataProvider('searches')]
    public function testFindsEveryWordTyped(string $text, array $expected): void
    {
        $page = $this->search([['text', $text]]);

        self::assertSame(
            array_map(fn (string $name): int => $this->ids[$name], $expected),
            $this->fixture->ids($page)
        );
    }

    public function testAKeyNobodyServesIsSearchedAsTheTextTyped(): void
    {
        $page = $this->search([['mode', 'maintenance']]);

        self::assertSame([$this->ids['mode']], $this->fixture->ids($page), 'Searched as "mode: maintenance"');
    }

    /**
     * Search the test's window
     *
     * @param list<array{0: string, 1: string}> $chips
     * @return \Hryvinskyi\ActivityLog\Api\Data\Search\SearchPageInterface
     */
    private function search(array $chips): \Hryvinskyi\ActivityLog\Api\Data\Search\SearchPageInterface
    {
        $engine = Bootstrap::getObjectManager()->get(ActivitySearchInterface::class);
        self::assertInstanceOf(ActivitySearchInterface::class, $engine);

        return $engine->search($this->fixture->request(self::FROM_MS, self::FROM_MS + 1000, $chips));
    }
}
