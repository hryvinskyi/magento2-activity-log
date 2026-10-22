<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\ResourceModel;

use Hryvinskyi\ActivityLog\Api\Search\SubjectLookupInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Helper as DbHelper;
use Magento\Framework\DB\Select;

/**
 * Subjects by recorded name, read from the journal
 *
 * The subject index narrows the rows to the types asked for; the names themselves are read row by row, so a text
 * that matches nothing reads every activity of those types once. The lookup stops at the limit, in no set order.
 */
class SubjectLookup implements SubjectLookupInterface
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param DbHelper $dbHelper
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly DbHelper $dbHelper
    ) {
    }

    /**
     * @inheritDoc
     */
    public function subjectsLike(array $subjectTypes, string $labelPart, int $limit = self::MAX_SUBJECTS): array
    {
        $subjectTypes = array_values(array_unique($subjectTypes));
        if ($subjectTypes === [] || trim($labelPart) === '') {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->distinct()
                ->from(
                    ['journal' => $this->resourceConnection->getTableName(Activity::TABLE)],
                    ['subject_type', 'subject_id']
                )
                ->where('journal.subject_type IN (?)', $subjectTypes)
                ->where('journal.subject_id IS NOT NULL')
                ->where(
                    $connection->quoteInto(
                        'journal.subject_label LIKE ?',
                        $this->dbHelper->escapeLikeValue($labelPart, ['position' => 'any'])
                    ),
                    null,
                    Select::TYPE_CONDITION
                )
                ->limit(max(1, min($limit, self::MAX_SUBJECTS)))
        );

        $subjects = [];
        foreach ($rows as $row) {
            if (is_array($row) && is_string($row['subject_type'] ?? null) && is_scalar($row['subject_id'] ?? null)) {
                $subjects[] = ['type' => $row['subject_type'], 'id' => (string) $row['subject_id']];
            }
        }

        return $subjects;
    }
}
