<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Actor\NameLookup;

use Hryvinskyi\ActivityLog\Model\Actor\AccountNameLookupInterface;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Integration\Api\IntegrationServiceInterface;

/**
 * Integration name by integration id
 */
class IntegrationNameLookup implements AccountNameLookupInterface, ResetAfterRequestInterface
{
    /**
     * @var array<int, string|null>
     */
    private array $names = [];

    /**
     * @param IntegrationServiceInterface $integrationService
     */
    public function __construct(
        private readonly IntegrationServiceInterface $integrationService
    ) {
    }

    /**
     * @inheritDoc
     */
    public function nameOf(int $id): ?string
    {
        if (array_key_exists($id, $this->names)) {
            return $this->names[$id];
        }

        $name = $this->integrationService->get($id)->getData('name');

        return $this->names[$id] = is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->names = [];
    }
}
