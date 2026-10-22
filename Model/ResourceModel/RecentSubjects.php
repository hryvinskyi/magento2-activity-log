<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\ResourceModel;

use Hryvinskyi\ActivityLog\Api\Search\RecentSubjectsInterface;
use Hryvinskyi\ActivityLog\Api\Search\SuggestionProviderInterface;
use Hryvinskyi\ActivityLog\Model\Search\ChangeChannels;
use Hryvinskyi\ActivityLog\Model\Search\Suggestion\RecentWindow;
use Magento\Framework\App\ResourceConnection;

/**
 * Recently changed subjects from the journal: one grouped query over the change channels' recent activities on the
 * (channel, time) index, then the latest recorded names by primary key
 */
class RecentSubjects implements RecentSubjectsInterface
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param RecentWindow $window
     * @param ChangeChannels $changeChannels
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly RecentWindow $window,
        private readonly ChangeChannels $changeChannels
    ) {
    }

    /**
     * @inheritDoc
     */
    public function recentlyChanged(
        array $subjectTypes,
        int $limit = SuggestionProviderInterface::MAX_SUGGESTIONS
    ): array {
        $channels = $this->changeChannels->codes();
        if ($subjectTypes === [] || $channels === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(Activity::TABLE);
        $groups = $connection->fetchAll(
            $connection->select()
                ->from($table, [
                    'subject_type',
                    'subject_id',
                    'occurrences' => 'COUNT(*)',
                    'latest' => 'MAX(entity_id)',
                ])
                ->where('channel IN (?)', $channels)
                ->where('occurred_at_ms >= ?', $this->window->sinceMs())
                ->where('subject_type IN (?)', $subjectTypes)
                ->where('subject_id IS NOT NULL')
                ->group(['subject_type', 'subject_id'])
                ->order('latest DESC')
                ->limit(max(1, min($limit, SuggestionProviderInterface::MAX_SUGGESTIONS)))
        );

        $rows = [];
        foreach ($groups as $group) {
            $row = is_array($group) ? $this->group($group) : null;
            if ($row !== null) {
                $rows[] = $row;
            }
        }
        $latest = array_column($rows, 'latest');
        $labels = $latest === [] ? [] : $connection->fetchPairs(
            $connection->select()
                ->from($table, ['entity_id', 'subject_label'])
                ->where('entity_id IN (?)', $latest)
        );

        $subjects = [];
        foreach ($rows as $row) {
            $label = $labels[$row['latest']] ?? null;
            $subjects[] = [
                'type' => $row['type'],
                'id' => $row['id'],
                'label' => is_scalar($label) ? trim((string) $label) : '',
                'count' => $row['count'],
            ];
        }

        return $subjects;
    }

    /**
     * One grouped row, typed, or null when it names no subject
     *
     * @param array<mixed> $group
     * @return array{type: string, id: string, count: int, latest: int}|null
     */
    private function group(array $group): ?array
    {
        $type = $group['subject_type'] ?? null;
        $id = $group['subject_id'] ?? null;
        $count = $group['occurrences'] ?? null;
        $latest = $group['latest'] ?? null;
        if (!is_scalar($type) || !is_scalar($id) || trim((string) $id) === '' || !is_numeric($count)
            || !is_numeric($latest)
        ) {
            return null;
        }

        return ['type' => (string) $type, 'id' => (string) $id, 'count' => (int) $count, 'latest' => (int) $latest];
    }
}
