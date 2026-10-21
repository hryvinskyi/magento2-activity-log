<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model;

use Hryvinskyi\ActivityLog\Api\ActivityRecorderInterface;
use Hryvinskyi\ActivityLog\Api\ConfigInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActivityInterface;
use Hryvinskyi\ActivityLog\Model\ResourceModel\Activity as ActivityResource;
use Hryvinskyi\ActivityLog\Model\ResourceModel\ActivityRowMapper;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Psr\Log\LoggerInterface;

/**
 * Inserts activities into the journal
 */
class ActivityRecorder implements ActivityRecorderInterface
{
    /**
     * @param ConfigInterface $config
     * @param ResourceConnection $resourceConnection
     * @param ActivityRowMapper $rowMapper
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly ResourceConnection $resourceConnection,
        private readonly ActivityRowMapper $rowMapper,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function record(ActivityInterface $activity): ?int
    {
        if (!$this->config->isEnabled()) {
            return null;
        }

        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName(ActivityResource::TABLE);
            $connection->insert($table, $this->rowMapper->toRow($activity));
            $id = $connection instanceof Mysql ? $connection->lastInsertId($table) : null;

            return is_numeric($id) ? (int) $id : null;
        } catch (\Throwable $exception) {
            $this->logger->error('Activity log could not record an activity.', [
                'channel' => $activity->getChannel(),
                'action' => $activity->getAction(),
                'exception' => $exception,
            ]);

            return null;
        }
    }
}
