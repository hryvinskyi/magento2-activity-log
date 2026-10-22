<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Search\Fake;

use Magento\Framework\Cache\CacheConstants;
use Magento\Framework\Cache\FrontendInterface;

/**
 * A cache frontend that keeps entries in memory, with the lifetime each was saved with, for tests to read
 */
class MemoryCache implements FrontendInterface
{
    /**
     * @var array<string, array{data: string, tags: array<mixed>, lifetime: mixed}>
     */
    public array $entries = [];

    /**
     * @inheritDoc
     */
    public function test($identifier)
    {
        return isset($this->entries[$identifier]) ? 1 : false;
    }

    /**
     * @inheritDoc
     */
    public function load($identifier)
    {
        return $this->entries[$identifier]['data'] ?? false;
    }

    /**
     * @inheritDoc
     *
     * @param mixed $data
     * @param mixed $identifier
     * @param array<mixed> $tags
     * @param mixed $lifeTime
     */
    public function save($data, $identifier, array $tags = [], $lifeTime = null)
    {
        $this->entries[(string) (is_scalar($identifier) ? $identifier : '')] = [
            'data' => is_scalar($data) ? (string) $data : '',
            'tags' => $tags,
            'lifetime' => $lifeTime,
        ];

        return true;
    }

    /**
     * @inheritDoc
     */
    public function remove($identifier)
    {
        unset($this->entries[$identifier]);

        return true;
    }

    /**
     * @inheritDoc
     *
     * @param mixed $mode
     * @param array<mixed> $tags
     */
    public function clean($mode = CacheConstants::CLEANING_MODE_ALL, array $tags = [])
    {
        $this->entries = [];

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getBackend()
    {
        throw new \LogicException('The memory cache has no backend.');
    }

    /**
     * @inheritDoc
     */
    public function getLowLevelFrontend()
    {
        throw new \LogicException('The memory cache has no low-level frontend.');
    }
}
