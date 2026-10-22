<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Journal;

use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Hryvinskyi\ActivityLog\Model\Presentation\ActorTypeLabels;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Helper as DbHelper;

/**
 * "by": a kind of actor, by its code or label, on the indexed type column; a name in double quotes is that actor's
 * name exactly ('by: "admin"' finds the user named admin, never every admin); anything else is a name the actor's
 * name contains. No index serves a name, so a name is matched among the newest candidates only.
 */
class ActorCondition implements JournalConditionInterface
{
    private const EXACT_PATTERN = '/^"(.+)"$/sD';

    /**
     * @param ResourceConnection $resourceConnection
     * @param ActorTypeLabels $actorTypeLabels
     * @param DbHelper $dbHelper
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ActorTypeLabels $actorTypeLabels,
        private readonly DbHelper $dbHelper
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return 'by';
    }

    /**
     * @inheritDoc
     */
    public function isResidual(string $value): bool
    {
        return $this->exactName($value) !== null || $this->actorType($value) === null;
    }

    /**
     * @inheritDoc
     */
    public function condition(string $value, string $alias): ?string
    {
        $connection = $this->resourceConnection->getConnection();
        $name = $this->exactName($value);
        if ($name !== null) {
            return $connection->quoteInto($alias . '.actor_name = ?', $name);
        }

        $type = $this->actorType($value);
        if ($type !== null) {
            return $connection->quoteInto($alias . '.actor_type = ?', $type->value);
        }

        return $connection->quoteInto(
            $alias . '.actor_name LIKE ?',
            $this->dbHelper->escapeLikeValue($value, ['position' => 'any'])
        );
    }

    /**
     * The name a value in double quotes names exactly, or null for any other value
     *
     * @param string $value
     * @return string|null
     */
    private function exactName(string $value): ?string
    {
        return preg_match(self::EXACT_PATTERN, $value, $exact) === 1 && trim($exact[1]) !== '' ? $exact[1] : null;
    }

    /**
     * The kind of actor the value names by code or label, if any
     *
     * @param string $value
     * @return ActorType|null
     */
    private function actorType(string $value): ?ActorType
    {
        $wanted = mb_strtolower($value);
        foreach (ActorType::cases() as $type) {
            if ($type->value === $wanted || mb_strtolower((string) $this->actorTypeLabels->label($type)) === $wanted) {
                return $type;
            }
        }

        return null;
    }
}
