<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Search;

use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Hryvinskyi\ActivityLog\Api\Data\Outcome;
use Hryvinskyi\ActivityLog\Api\Data\Search\EventDetailInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\EventViewInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\Search\Verb;
use Hryvinskyi\ActivityLog\Api\Search\EventDetailProviderInterface;
use Hryvinskyi\ActivityLog\Model\Data\Search\EventDetail;
use Hryvinskyi\ActivityLog\Model\Data\Search\EventView;
use Hryvinskyi\ActivityLog\Model\Data\Search\LinkPolicy;
use Hryvinskyi\ActivityLog\Model\Search\Detail\DetailProviderPool;
use Hryvinskyi\ActivityLog\Model\Search\Detail\EventDetails;
use Hryvinskyi\ActivityLog\Model\Search\EventViewAssembler;
use Hryvinskyi\ActivityLog\Model\Search\SiblingCounter;
use Hryvinskyi\ActivityLog\Test\Unit\Model\Search\Fake\RecordingLogger;
use Magento\Backend\Model\UrlInterface;
use PHPUnit\Framework\TestCase;

/**
 * A page of journal rows becomes views with their operation's size, the recording module's details and a link
 */
class EventViewAssemblerTest extends TestCase
{
    /**
     * @var RecordingLogger
     */
    private RecordingLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
    }

    public function testAViewCarriesTheRowItsOperationAndItsDetailPage(): void
    {
        $views = $this->assembler([], ['op-one-request' => 3])->assemble([
            $this->row(41, 'zz_plain', 'op-one-request', [
                'actor_type' => 'integration',
                'actor_name' => 'Sync job',
                'via_client' => 'Agent',
                'via_version' => '1.2',
                'via_channel' => 'zz_agents',
                'action' => 'Stock updated',
                'outcome' => 'denied',
                'message' => 'Not allowed',
                'duration_ms' => '12.500',
                'ip' => '10.0.0.1',
                'subject_type' => 'thing',
                'subject_id' => '7',
                'subject_label' => 'Thing #7',
            ]),
        ]);

        self::assertCount(1, $views);
        $view = $views[0];
        self::assertSame([41, 1_700_000_041_000, 'zz_plain', 'op-one-request'], [
            $view->getActivityId(),
            $view->getOccurredAtMs(),
            $view->getChannel(),
            $view->getCorrelationId(),
        ]);
        self::assertSame([ActorType::Integration, 'Sync job', 'Agent', '1.2', 'zz_agents'], [
            $view->getActorType(),
            $view->getActorName(),
            $view->getViaClient(),
            $view->getViaVersion(),
            $view->getViaChannel(),
        ]);
        self::assertSame(['Stock updated', Outcome::Denied, 'Not allowed', 12.5, '10.0.0.1'], [
            $view->getAction(),
            $view->getOutcome(),
            $view->getMessage(),
            $view->getDurationMs(),
            $view->getIp(),
        ]);
        self::assertSame(['thing', '7', 'Thing #7'], [
            $view->getSubjectType(),
            $view->getSubjectId(),
            $view->getSubjectLabel(),
        ]);
        self::assertSame(2, $view->getSiblingCount(), 'The other two activities of its operation');
        self::assertNull($view->getDetail());
        self::assertSame(Verb::Performed, $view->getVerb(), 'Without details an activity performed its action');
        self::assertSame('https://admin.test/hryvinskyi_activitylog/activity/view/id/41/', $view->getDetailUrl());
    }

    public function testEachProviderIsAskedOnceForAllTheActivitiesOfItsChannels(): void
    {
        $entities = $this->provider(['zz_entity', 'zz_config']);
        $other = $this->provider(['zz_other']);

        $views = $this->assembler([$entities, $other])->assemble([
            $this->row(1, 'zz_entity'),
            $this->row(2, 'zz_plain'),
            $this->row(3, 'zz_config'),
            $this->row(4, 'zz_other'),
            $this->row(5, 'zz_entity'),
        ]);

        self::assertSame([[1, 3, 5]], $entities->calls);
        self::assertSame([[4]], $other->calls);
        self::assertSame([true, false, true, true, true], array_map(
            static fn ($view): bool => $view->getDetail() !== null,
            $views
        ));
        self::assertSame(Verb::Changed, $views[0]->getVerb());
        self::assertSame([1, 2, 3, 4, 5], array_map(static fn ($view): int => $view->getActivityId(), $views));
    }

    public function testEachProviderGetsTheSubjectNamesTheJournalRecordedForItsActivities(): void
    {
        $entities = $this->provider(['zz_entity']);

        $this->assembler([$entities])->assemble([
            $this->row(1, 'zz_entity', 'op-test-0001', ['subject_type' => 'thing', 'subject_label' => 'Thing Bag']),
            $this->row(2, 'zz_plain', 'op-test-0001', ['subject_type' => 'thing', 'subject_label' => 'Thing Other']),
            $this->row(3, 'zz_entity', 'op-test-0001', ['subject_type' => 'thing', 'subject_label' => '']),
            $this->row(4, 'zz_entity'),
        ]);

        self::assertSame([[1, 3, 4]], $entities->calls);
        self::assertSame([[1 => 'Thing Bag']], $entities->labels, 'Only its own activities, and only real names');
    }

    public function testTheFirstProviderThatSupportsAChannelServesIt(): void
    {
        $first = $this->provider(['zz_entity']);
        $second = $this->provider(['zz_entity']);

        $this->assembler([$first, $second])->assemble([$this->row(1, 'zz_entity')]);

        self::assertSame([[1]], $first->calls);
        self::assertSame([], $second->calls);
    }

    public function testAThrowingProviderIsLoggedAndItsActivitiesShowWithoutDetails(): void
    {
        $failing = $this->provider(['zz_broken'], fails: true);
        $working = $this->provider(['zz_entity']);

        $views = $this->assembler([$failing, $working])->assemble([
            $this->row(1, 'zz_broken'),
            $this->row(2, 'zz_entity'),
        ]);

        self::assertNull($views[0]->getDetail());
        self::assertNotNull($views[1]->getDetail());
        self::assertCount(1, $this->logger->records);
        self::assertSame('error', $this->logger->records[0]['level']);
        self::assertSame([1], $this->logger->records[0]['context']['activity_ids'] ?? null);
        self::assertInstanceOf(\RuntimeException::class, $this->logger->records[0]['context']['exception'] ?? null);
    }

    public function testAnswersForActivitiesNotAskedAboutAreIgnored(): void
    {
        $generous = $this->provider(['zz_entity'], extraId: 99);

        $views = $this->assembler([$generous])->assemble([$this->row(1, 'zz_entity')]);

        self::assertCount(1, $views);
        self::assertSame(1, $views[0]->getActivityId());
    }

    public function testAnOperationTheJournalNoLongerCountsHasNoSiblings(): void
    {
        $views = $this->assembler([], [])->assemble([$this->row(1, 'zz_plain', 'op-gone-meanwhile')]);

        self::assertSame(0, $views[0]->getSiblingCount());
    }

    public function testAnEmptyPageAsksNobody(): void
    {
        $provider = $this->provider(['zz_entity']);

        self::assertSame([], $this->assembler([$provider])->assemble([]));
        self::assertSame([], $provider->calls);
    }

    /**
     * An assembler over these providers and operation sizes
     *
     * @param list<EventDetailProviderInterface> $providers
     * @param array<string, int> $perOperation
     * @return EventViewAssembler
     */
    private function assembler(array $providers, array $perOperation = []): EventViewAssembler
    {
        $views = $this->createMock(EventViewInterfaceFactory::class);
        $views->method('create')->willReturnCallback(static fn (): EventView => new EventView(new LinkPolicy()));

        $siblings = $this->createMock(SiblingCounter::class);
        $siblings->method('activitiesPerOperation')->willReturn($perOperation);

        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn (string $route, array $params): string => sprintf(
                'https://admin.test/%s/id/%s/',
                $route,
                is_scalar($params['id'] ?? null) ? (string) $params['id'] : ''
            )
        );

        $pool = new DetailProviderPool(array_combine(
            array_map(static fn (int $i): string => 'provider' . $i, array_keys($providers)),
            $providers
        ));

        return new EventViewAssembler($views, $siblings, new EventDetails($pool, $this->logger), $url);
    }

    /**
     * A journal row of a result page
     *
     * @param int $id
     * @param string $channel
     * @param string $correlationId
     * @param array<string, mixed> $columns
     * @return array<string, mixed>
     */
    private function row(int $id, string $channel, string $correlationId = 'op-test-0001', array $columns = []): array
    {
        return $columns + [
            'entity_id' => $id,
            'occurred_at_ms' => 1_700_000_000_000 + $id * 1000,
            'channel' => $channel,
            'correlation_id' => $correlationId,
            'actor_type' => 'admin',
            'actor_name' => 'Tester',
            'action' => 'Saved',
            'outcome' => 'success',
        ];
    }

    /**
     * A provider recording what it is asked, answering a "changed" detail for each id
     *
     * @param list<string> $channels
     * @param bool $fails
     * @param int|null $extraId An id it also answers for, unasked
     * @return EventDetailProviderInterface&object{calls: list<list<int>>, labels: list<array<int, string>>}
     */
    private function provider(array $channels, bool $fails = false, ?int $extraId = null): EventDetailProviderInterface
    {
        return new class ($channels, $fails, $extraId) implements EventDetailProviderInterface {
            /**
             * @var list<list<int>>
             */
            public array $calls = [];

            /**
             * @var list<array<int, string>>
             */
            public array $labels = [];

            /**
             * @param list<string> $channels
             * @param bool $fails
             * @param int|null $extraId
             */
            public function __construct(
                private readonly array $channels,
                private readonly bool $fails,
                private readonly ?int $extraId
            ) {
            }

            public function supports(string $channel): bool
            {
                return in_array($channel, $this->channels, true);
            }

            /**
             * @param list<int> $activityIds
             * @param array<int, string> $subjectLabels
             * @return array<int, EventDetailInterface>
             */
            public function detailsFor(array $activityIds, array $subjectLabels = []): array
            {
                $this->calls[] = $activityIds;
                $this->labels[] = $subjectLabels;
                if ($this->fails) {
                    throw new \RuntimeException('The provider broke.');
                }

                $details = [];
                foreach ($this->extraId === null ? $activityIds : [...$activityIds, $this->extraId] as $id) {
                    $details[$id] = (new EventDetail(new LinkPolicy()))->withVerb(Verb::Changed);
                }

                return $details;
            }
        };
    }
}
