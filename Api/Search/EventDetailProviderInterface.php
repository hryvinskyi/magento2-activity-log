<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\EventDetailInterface;

/**
 * Event details for the activities of the channels a module records
 *
 * A module adds its provider to the "detailProviders" argument of
 * Hryvinskyi\ActivityLog\Model\Search\Detail\DetailProviderPool. A result page asks each provider once, with the ids
 * of all its activities on the page that belong to a channel the provider supports (the first provider in the pool
 * that supports a channel serves it). Activities without details — no provider, no answer for the id, or a provider
 * that throws — are shown from the journal alone; a throwing provider is logged, never shown to the admin.
 *
 * @api
 */
interface EventDetailProviderInterface
{
    /**
     * Whether this provider describes activities of the channel
     *
     * @param string $channel Channel code
     * @return bool
     */
    public function supports(string $channel): bool;

    /**
     * Details of the activities it can describe, keyed by activity id; ids it cannot describe are left out
     *
     * The subject names come keyed by activity id, one for each of the activities the journal recorded a name for,
     * for a provider that names the subject differently in the sentence (EventDetailInterface::withSubjectLabel()).
     *
     * @param list<int> $activityIds
     * @param array<int,string> $subjectLabels The subject names the journal recorded
     * @return array<int, EventDetailInterface>
     */
    public function detailsFor(array $activityIds, array $subjectLabels = []): array;
}
