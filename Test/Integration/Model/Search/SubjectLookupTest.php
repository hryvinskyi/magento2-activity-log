<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model\Search;

use Hryvinskyi\ActivityLog\Api\Search\SubjectLookupInterface;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\SearchFixture;
use Magento\TestFramework\Fixture\DbIsolation;
use PHPUnit\Framework\TestCase;

/**
 * Subjects by the name the journal recorded for them, through the container's lookup
 */
#[DbIsolation(true)]
class SubjectLookupTest extends TestCase
{
    private const NOW_MS = 1_792_500_000_000;

    /**
     * @var SearchFixture
     */
    private SearchFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new SearchFixture();
        $this->fixture->clear();
    }

    public function testFindsEachSubjectOnceByPartOfItsRecordedName(): void
    {
        $this->subject('record', '7', 'Record aal-tote · Retired Tote');
        $this->subject('record', '7', 'Record aal-tote · Retired Tote · price');
        $this->subject('record', '8', 'Record AAL-TOTE-2 · Other');
        $this->subject('other', '9', 'Other aal-tote');
        $this->subject('record', null, 'Record aal-tote without an id');

        $found = $this->lookup()->subjectsLike(['record'], 'aal-tote');
        usort($found, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));

        self::assertSame([['type' => 'record', 'id' => '7'], ['type' => 'record', 'id' => '8']], $found);
        self::assertCount(3, $this->lookup()->subjectsLike(['record', 'other'], 'tote'));
    }

    public function testWildcardsAreTakenLiterally(): void
    {
        $this->subject('record', '1', 'Record 50% off');
        $this->subject('record', '2', 'Record 50 and off');

        self::assertSame([['type' => 'record', 'id' => '1']], $this->lookup()->subjectsLike(['record'], '50%'));
        self::assertSame([], $this->lookup()->subjectsLike(['record'], '5_ '));
    }

    public function testTheLimitIsKeptAndNothingIsAskedOfNoTypeOrNoText(): void
    {
        for ($id = 1; $id <= 60; $id++) {
            $this->subject('record', (string) $id, 'Record batch-' . $id);
        }

        self::assertCount(3, $this->lookup()->subjectsLike(['record'], 'batch-', 3));
        self::assertCount(
            SubjectLookupInterface::MAX_SUBJECTS,
            $this->lookup()->subjectsLike(['record'], 'batch-', 500)
        );
        self::assertSame([], $this->lookup()->subjectsLike([], 'batch-'));
        self::assertSame([], $this->lookup()->subjectsLike(['record'], ' '));
    }

    /**
     * @param string $type
     * @param string|null $id
     * @param string $label
     * @return void
     */
    private function subject(string $type, ?string $id, string $label): void
    {
        $this->fixture->insert(self::NOW_MS, ['subject_type' => $type, 'subject_id' => $id, 'subject_label' => $label]);
    }

    /**
     * @return SubjectLookupInterface
     */
    private function lookup(): SubjectLookupInterface
    {
        return SearchFixture::service(SubjectLookupInterface::class);
    }
}
