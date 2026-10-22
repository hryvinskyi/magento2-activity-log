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
use Hryvinskyi\ActivityLog\Model\Data\Search\EventView;
use Hryvinskyi\ActivityLog\Model\Data\Search\LinkPolicy;
use PHPUnit\Framework\TestCase;

/**
 * A result's verb comes from its details, its numbers are in range and its link is safe to follow
 */
class EventViewTest extends TestCase
{
    public function testTheVerbIsTheDetailsVerbElsePerformed(): void
    {
        $view = $this->view();

        self::assertSame(Verb::Performed, $view->getVerb());
        self::assertSame(
            Verb::Deleted,
            $view->withDetail((new EventDetail(new LinkPolicy()))->withVerb(Verb::Deleted))->getVerb()
        );
    }

    public function testASubjectWithoutATypeIsNoSubject(): void
    {
        $view = $this->view()->withSubject(null, '7', 'Leftover');

        self::assertSame(
            [null, null, null],
            [$view->getSubjectType(), $view->getSubjectId(), $view->getSubjectLabel()]
        );
    }

    /**
     * @return array<string, array{callable(EventView): mixed}>
     */
    public static function refused(): array
    {
        return [
            'no id' => [static fn (EventView $view): mixed => $view->withActivity(0, 1, 'admin', 'op-x-0001')],
            'negative time' => [static fn (EventView $view): mixed => $view->withActivity(1, -1, 'admin', 'op-x-0001')],
            'negative siblings' => [static fn (EventView $view): mixed => $view->withSiblingCount(-1)],
            'script link' => [static fn (EventView $view): mixed => $view->withDetailUrl('javascript:void(0)')],
        ];
    }

    /**
     * @param callable(EventView): mixed $change
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('refused')]
    public function testOutOfRangeValuesAreRefused(callable $change): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $change($this->view());
    }

    /**
     * @return EventView
     */
    private function view(): EventView
    {
        return new EventView(new LinkPolicy());
    }
}
