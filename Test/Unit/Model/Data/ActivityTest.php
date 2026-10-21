<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Data;

use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Hryvinskyi\ActivityLog\Api\Data\Outcome;
use Hryvinskyi\ActivityLog\Model\Data\Activity;
use Hryvinskyi\ActivityLog\Model\Data\Actor;
use Hryvinskyi\ActivityLog\Model\Data\Origin;
use Hryvinskyi\ActivityLog\Model\Data\Subject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ActivityTest extends TestCase
{
    public function testKeepsWhatItWasGiven(): void
    {
        $actor = new Actor(ActorType::Admin, 'jane.doe', '7', 'Desk Assistant', '1.4.2');
        $origin = new Origin(1, '10.0.4.21', 'Desk Assistant/1.4.2');
        $subject = new Subject('product', 'Product #1245 · SKU 24-MB01', '1245');

        $activity = new Activity(
            'a1f3c9e7a1f3c9e7',
            'mcp',
            'execute_ability catalog.product_repository.save',
            $actor,
            Outcome::Success,
            $origin,
            1792500728120,
            $subject,
            null,
            412.5,
            120431
        );

        self::assertSame(120431, $activity->getId());
        self::assertSame('a1f3c9e7a1f3c9e7', $activity->getCorrelationId());
        self::assertSame('mcp', $activity->getChannel());
        self::assertSame(Outcome::Success, $activity->getOutcome());
        self::assertSame($actor, $activity->getActor());
        self::assertSame($subject, $activity->getSubject());
        self::assertSame(412.5, $activity->getDurationMs());
        self::assertSame(1792500728120, $activity->getOccurredAtMs());
    }

    /**
     * @return array<string, array{string, string, string, int, float|null}>
     */
    public static function brokenRules(): array
    {
        return [
            'short correlation id' => ['abc', 'rest', 'GET /V1/orders', 1, null],
            'correlation id with spaces' => ['abc def ghi', 'rest', 'GET /V1/orders', 1, null],
            'uppercase channel' => ['a1f3c9e7', 'REST', 'GET /V1/orders', 1, null],
            'channel starting with a digit' => ['a1f3c9e7', '1rest', 'GET /V1/orders', 1, null],
            'blank action' => ['a1f3c9e7', 'rest', '   ', 1, null],
            'action too long' => ['a1f3c9e7', 'rest', str_repeat('a', 256), 1, null],
            'zero time' => ['a1f3c9e7', 'rest', 'GET /V1/orders', 0, null],
            'negative duration' => ['a1f3c9e7', 'rest', 'GET /V1/orders', 1, -1.0],
        ];
    }

    #[DataProvider('brokenRules')]
    public function testRefusesAnActivityThatBreaksTheJournalRules(
        string $correlationId,
        string $channel,
        string $action,
        int $occurredAtMs,
        ?float $durationMs
    ): void {
        $this->expectException(\InvalidArgumentException::class);

        new Activity(
            $correlationId,
            $channel,
            $action,
            new Actor(ActorType::Cron, 'cron'),
            Outcome::Success,
            new Origin(),
            $occurredAtMs,
            null,
            null,
            $durationMs
        );
    }

    public function testActorRefusesAClientVersionWithoutAClient(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Actor(ActorType::Admin, 'jane.doe', '7', null, '1.4.2');
    }

    public function testActorRefusesABlankName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Actor(ActorType::Admin, ' ');
    }

    public function testSubjectRefusesAMalformedType(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Subject('Product Type', 'Product #1');
    }

    public function testOriginRefusesAnOverlongUserAgent(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Origin(null, null, str_repeat('x', 513));
    }
}
