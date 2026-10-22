<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Data\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\Verb;
use Hryvinskyi\ActivityLog\Model\Data\Search\EventDetail;
use Hryvinskyi\ActivityLog\Model\Data\Search\FieldChange;
use Hryvinskyi\ActivityLog\Model\Data\Search\LinkPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Event details hold codes, counts and safe links only
 */
class EventDetailTest extends TestCase
{
    public function testHoldsWhatItIsGiven(): void
    {
        $field = (new FieldChange())->withField('price');

        $detail = $this->detail()
            ->withVerb(Verb::Created)
            ->withWhat(null, 24)
            ->withSubject('Record', 'https://shop.test/admin/records/edit/id/7/')
            ->withFields([$field], 23)
            ->withUnchangedCount(3);

        self::assertSame(Verb::Created, $detail->getVerb());
        self::assertSame([null, 24, 23, 3], [
            $detail->getWhat(),
            $detail->getFieldCount(),
            $detail->getMoreFieldCount(),
            $detail->getUnchangedCount(),
        ]);
        self::assertSame(['Record', 'https://shop.test/admin/records/edit/id/7/'], [
            $detail->getSubjectTypeLabel(),
            $detail->getSubjectUrl(),
        ]);
        self::assertSame([$field], $detail->getFields());
        self::assertNull($detail->getSubjectLabel(), 'Without a name of its own the recorded name is shown');
    }

    public function testSaysWhetherTheFieldsThatKeptTheirValueHaveRowsToOpen(): void
    {
        $recorded = $this->detail()->withUnchangedCount(3);
        $counted = $recorded->withUnchangedCount(39, false);

        self::assertSame([3, true], [$recorded->getUnchangedCount(), $recorded->areUnchangedFieldsRecorded()]);
        self::assertSame([39, false], [$counted->getUnchangedCount(), $counted->areUnchangedFieldsRecorded()]);
        self::assertTrue($this->detail()->areUnchangedFieldsRecorded(), 'With nothing counted there is nothing amiss');
    }

    public function testNamesTheSubjectWithoutItsKindOnlyWhenGivenAName(): void
    {
        $named = $this->detail()->withSubjectLabel('SKU-1 · Bag');

        self::assertSame('SKU-1 · Bag', $named->getSubjectLabel());
        self::assertNull($this->detail()->getSubjectLabel());
        self::assertNull($named->withSubjectLabel('  ')->getSubjectLabel());
        self::assertNull($named->withSubjectLabel(null)->getSubjectLabel());
        self::assertSame('SKU-1 · Bag', $named->getSubjectLabel(), 'A copy leaves the original as it was');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeLinks(): array
    {
        return [
            'script' => ['javascript:alert(1)'],
            'data' => ['data:text/html,<b>x</b>'],
            'another site without a scheme' => ['//evil.test/x'],
            'relative' => ['records/edit'],
            'whitespace' => ["/admin/x\nSet-Cookie: a"],
            'empty' => [''],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeLinks')]
    public function testASubjectLinkTheBrowserMustNotFollowIsRefused(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->detail()->withSubject('Record', $url);
    }

    public function testASiteRootPathIsALink(): void
    {
        self::assertSame('/admin/records/edit/id/2/', $this->detail()
            ->withSubject(null, '/admin/records/edit/id/2/')->getSubjectUrl());
    }

    public function testARowThatIsNotAFieldChangeIsRefusedFromAnUntypedCaller(): void
    {
        $detail = $this->detail();

        $this->expectException(\InvalidArgumentException::class);

        (new \ReflectionMethod($detail, 'withFields'))->invoke($detail, [['name' => 'price']]);
    }

    public function testNegativeCountsAreRefused(): void
    {
        $refused = 0;
        foreach ([
            fn () => $this->detail()->withWhat('Price', -1),
            fn () => $this->detail()->withFields([], -1),
            fn () => $this->detail()->withUnchangedCount(-1),
            fn () => $this->detail()->withAutomaticCount(-1),
        ] as $call) {
            try {
                $call();
            } catch (\InvalidArgumentException) {
                $refused++;
            }
        }

        self::assertSame(4, $refused);
    }

    public function testCountsTheFieldsSetAutomaticallyAmongThoseTouched(): void
    {
        $detail = $this->detail()->withWhat(null, 4)->withAutomaticCount(3);

        self::assertSame(0, $this->detail()->getAutomaticCount(), 'None unless said');
        self::assertSame(3, $detail->getAutomaticCount());
        self::assertSame(4, $detail->withAutomaticCount(9)->getAutomaticCount(), 'Never more than the fields touched');
        self::assertSame(3, $detail->withWhat('Price', 4)->getAutomaticCount(), 'Kept by the other copies');
    }

    /**
     * @return EventDetail
     */
    private function detail(): EventDetail
    {
        return new EventDetail(new LinkPolicy());
    }
}
