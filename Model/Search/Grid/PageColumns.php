<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Grid;

use Hryvinskyi\ActivityLog\Model\Search\Detail\EventDetails;
use Hryvinskyi\ActivityLog\Model\Search\SiblingCounter;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The grid columns a page of journal rows gets after it is read: the size of each row's operation, and what changed
 *
 * One grouped count on the correlation index for the page's operations (the whole journal, whatever the search
 * hides), and one call per detail provider for the page's activities, so a page costs the same whatever column it is
 * sorted by. Filled for the rendered grid and for every page of an export alike. An activity whose details name no
 * scope shows the store view it was recorded in; the store view names are read once per request.
 */
class PageColumns implements ResetAfterRequestInterface
{
    public const OPERATION_SIZE = 'operation_size';

    /**
     * Store view names by id, once read
     *
     * @var array<int, string>|null
     */
    private ?array $storeNames = null;

    /**
     * @param SiblingCounter $siblingCounter
     * @param EventDetails $eventDetails
     * @param ChangeColumns $changeColumns
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly SiblingCounter $siblingCounter,
        private readonly EventDetails $eventDetails,
        private readonly ChangeColumns $changeColumns,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Add the columns to each row
     *
     * @param list<DataObject> $rows Journal rows with entity_id, channel, correlation_id and store_id
     * @return void
     */
    public function fill(array $rows): void
    {
        $channels = [];
        $correlationIds = [];
        foreach ($rows as $row) {
            $id = $row->getData('entity_id');
            if (is_numeric($id)) {
                $channels[(int) $id] = $this->text($row->getData('channel'));
            }
            $correlationIds[] = $this->text($row->getData('correlation_id'));
        }

        $operationSizes = $this->siblingCounter->activitiesPerOperation($correlationIds);
        $details = $this->eventDetails->forActivities($channels);

        foreach ($rows as $row) {
            $id = $row->getData('entity_id');
            $values = $this->changeColumns->values(is_numeric($id) ? $details[(int) $id] ?? null : null);
            $values[ChangeColumns::SCOPE] ??= $this->storeName($row->getData('store_id'));
            $values[self::OPERATION_SIZE] = $operationSizes[$this->text($row->getData('correlation_id'))] ?? 1;
            $row->addData($values);
        }
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->storeNames = null;
    }

    /**
     * The name of a store view, or null for none or the admin
     *
     * @param mixed $storeId
     * @return string|null
     */
    private function storeName(mixed $storeId): ?string
    {
        if ($this->storeNames === null) {
            $this->storeNames = [];
            foreach ($this->storeManager->getStores() as $store) {
                $this->storeNames[(int) $store->getId()] = (string) $store->getName();
            }
        }

        return is_numeric($storeId) ? $this->storeNames[(int) $storeId] ?? null : null;
    }

    /**
     * A column value as text
     *
     * @param mixed $value
     * @return string
     */
    private function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
