<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Actor\NameLookup;

use Hryvinskyi\ActivityLog\Model\Actor\AccountNameLookupInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * Customer email by customer id
 */
class CustomerNameLookup implements AccountNameLookupInterface, ResetAfterRequestInterface
{
    /**
     * @var array<int, string|null>
     */
    private array $names = [];

    /**
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository
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

        try {
            $email = $this->customerRepository->getById($id)->getEmail();
        } catch (LocalizedException) {
            $email = null;
        }

        return $this->names[$id] = is_string($email) && $email !== '' ? $email : null;
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->names = [];
    }
}
