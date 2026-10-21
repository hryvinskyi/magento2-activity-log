<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Actor\Resolver;

use Hryvinskyi\ActivityLog\Api\ActorResolverInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActorInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActorInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;

/**
 * A process nobody logged in to: the cron runner, or a command run from the shell
 */
class ProcessActorResolver implements ActorResolverInterface
{
    /**
     * @param State $appState
     * @param ActorInterfaceFactory $actorFactory
     * @param string $sapi
     */
    public function __construct(
        private readonly State $appState,
        private readonly ActorInterfaceFactory $actorFactory,
        private readonly string $sapi = PHP_SAPI
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(): ?ActorInterface
    {
        if ($this->areaCode() === Area::AREA_CRONTAB) {
            return $this->actorFactory->create(['type' => ActorType::Cron, 'name' => 'cron']);
        }

        if ($this->sapi === 'cli') {
            return $this->actorFactory->create(['type' => ActorType::Cli, 'name' => 'cli']);
        }

        return null;
    }

    /**
     * Current area, or null before one is set
     *
     * @return string|null
     */
    private function areaCode(): ?string
    {
        try {
            return $this->appState->getAreaCode();
        } catch (LocalizedException) {
            return null;
        }
    }
}
