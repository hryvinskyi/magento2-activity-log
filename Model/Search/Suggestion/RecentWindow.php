<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Suggestion;

use Hryvinskyi\ActivityLog\Api\MillisecondClockInterface;
use Hryvinskyi\ActivityLog\Model\ResourceModel\Activity;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * The stretch of the log suggestions are drawn from: the DAYS × 24 hours that end now
 *
 * A rolling window rather than calendar days: a suggestion list is kept for a few minutes and its counts are hints,
 * so the window needs no timezone, and every provider bounds its query by the same instant.
 *
 * A module that keeps rows of its own per activity bounds them by firstActivityId() instead of joining the journal:
 * activity ids grow as activities are written, so the window's activities are the ones from that id on, read from the
 * module's own activity id index.
 *
 * @api
 */
class RecentWindow implements ResetAfterRequestInterface
{
    /**
     * Length of the window in days
     */
    public const DAYS = 30;

    private const DAY_MS = 86_400_000;

    /**
     * Whether the window's first activity id was read in this request
     *
     * @var bool
     */
    private bool $firstActivityIdRead = false;

    /**
     * First activity id of the window, once read
     *
     * @var int|null
     */
    private ?int $firstActivityId = null;

    /**
     * @param MillisecondClockInterface $clock
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly MillisecondClockInterface $clock,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Start of the window, ms since the epoch: activities at or after it are recent
     *
     * @return int
     */
    public function sinceMs(): int
    {
        return max(0, $this->clock->nowMs() - self::DAYS * self::DAY_MS);
    }

    /**
     * The lowest id of a recent activity, or null when there is none; read once per request
     *
     * @return int|null
     */
    public function firstActivityId(): ?int
    {
        if (!$this->firstActivityIdRead) {
            $connection = $this->resourceConnection->getConnection();
            $id = $connection->fetchOne(
                $connection->select()
                    ->from($this->resourceConnection->getTableName(Activity::TABLE), ['MIN(entity_id)'])
                    ->where('occurred_at_ms >= ?', $this->sinceMs())
            );
            $this->firstActivityId = is_numeric($id) ? (int) $id : null;
            $this->firstActivityIdRead = true;
        }

        return $this->firstActivityId;
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->firstActivityIdRead = false;
        $this->firstActivityId = null;
    }
}
