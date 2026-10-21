<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Cron;

use Hryvinskyi\ActivityLog\Api\PurgerInterface;
use Psr\Log\LoggerInterface;

/**
 * Runs every registered purger; one purger failing does not stop the others
 */
class ApplyRetention
{
    /**
     * @param LoggerInterface $logger
     * @param array<string,PurgerInterface> $purgers
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly array $purgers = []
    ) {
    }

    /**
     * Cron entry point
     *
     * @return void
     */
    public function execute(): void
    {
        foreach ($this->purgers as $name => $purger) {
            try {
                $removed = $purger->purge();
                if ($removed > 0) {
                    $this->logger->info(sprintf('Activity log retention: %s removed %d activities.', $name, $removed));
                }
            } catch (\Throwable $exception) {
                $this->logger->error(
                    sprintf('Activity log retention: %s failed.', $name),
                    ['exception' => $exception]
                );
            }
        }
    }
}
