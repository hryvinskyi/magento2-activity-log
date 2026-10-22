<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Detail;

use Hryvinskyi\ActivityLog\Api\Data\Search\EventDetailInterface;
use Hryvinskyi\ActivityLog\Api\Search\EventDetailProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Event details of a page of activities, one call per provider
 *
 * Each provider gets the ids of all the page's activities of the channels it serves, at once, with the subject names
 * the journal recorded for them. A provider that throws
 * is logged and its activities are shown without details: one module's fault never empties the page. Answers for
 * ids that were not asked are ignored.
 */
class EventDetails
{
    /**
     * @param DetailProviderPool $providerPool
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly DetailProviderPool $providerPool,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Details of the activities whose providers answered, keyed by activity id
     *
     * @param array<int,string> $channelsById Channel code of each activity, keyed by activity id
     * @param array<int,string> $subjectLabelsById Subject name the journal recorded, keyed by activity id
     * @return array<int, EventDetailInterface>
     */
    public function forActivities(array $channelsById, array $subjectLabelsById = []): array
    {
        $providers = [];
        $batches = [];
        foreach ($channelsById as $activityId => $channel) {
            $provider = $this->providerPool->providerFor($channel);
            if ($provider === null) {
                continue;
            }
            $key = spl_object_id($provider);
            $providers[$key] = $provider;
            $batches[$key][] = $activityId;
        }

        $details = [];
        foreach ($batches as $key => $activityIds) {
            $labels = array_intersect_key($subjectLabelsById, array_flip($activityIds));
            foreach ($this->ask($providers[$key], $activityIds, $labels) as $activityId => $detail) {
                $details[$activityId] = $detail;
            }
        }

        return $details;
    }

    /**
     * One provider's answers for the ids it was asked about, or none when it fails
     *
     * @param EventDetailProviderInterface $provider
     * @param list<int> $activityIds
     * @param array<int,string> $subjectLabels
     * @return array<int, EventDetailInterface>
     */
    private function ask(EventDetailProviderInterface $provider, array $activityIds, array $subjectLabels): array
    {
        try {
            $answers = $provider->detailsFor($activityIds, $subjectLabels);
        } catch (\Throwable $failure) {
            $this->logger->error(
                'Event details could not be read; the activities are shown from the journal alone.',
                ['provider' => get_class($provider), 'activity_ids' => $activityIds, 'exception' => $failure]
            );

            return [];
        }

        return array_intersect_key($answers, array_flip($activityIds));
    }
}
