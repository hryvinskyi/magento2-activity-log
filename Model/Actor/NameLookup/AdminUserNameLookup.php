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
use Magento\User\Model\ResourceModel\User\CollectionFactory;

/**
 * Admin username by admin user id
 */
class AdminUserNameLookup implements AccountNameLookupInterface, ResetAfterRequestInterface
{
    /**
     * @var array<int, string|null>
     */
    private array $names = [];

    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory
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

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('main_table.user_id', ['eq' => $id])->setPageSize(1);
        $username = $collection->getFirstItem()->getData('username');

        return $this->names[$id] = is_string($username) && $username !== '' ? $username : null;
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->names = [];
    }
}
