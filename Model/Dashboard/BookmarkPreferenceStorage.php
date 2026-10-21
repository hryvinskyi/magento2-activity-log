<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Dashboard;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Ui\Api\BookmarkManagementInterface;
use Magento\Ui\Api\BookmarkRepositoryInterface;
use Magento\Ui\Api\Data\BookmarkInterface;
use Magento\Ui\Api\Data\BookmarkInterfaceFactory;

/**
 * Keeps the choice in the admin user's UI bookmarks, where Magento keeps grid views
 */
class BookmarkPreferenceStorage implements PreferenceStorageInterface
{
    public const NAMESPACE = 'hryvinskyi_activity_log_dashboard';
    public const IDENTIFIER = 'current';

    /**
     * @param BookmarkManagementInterface $bookmarkManagement
     * @param BookmarkRepositoryInterface $bookmarkRepository
     * @param BookmarkInterfaceFactory $bookmarkFactory
     * @param UserContextInterface $userContext
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly BookmarkManagementInterface $bookmarkManagement,
        private readonly BookmarkRepositoryInterface $bookmarkRepository,
        private readonly BookmarkInterfaceFactory $bookmarkFactory,
        private readonly UserContextInterface $userContext,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function load(): array
    {
        $config = $this->find()?->getConfig() ?? [];

        $periodDays = is_int($config['periodDays'] ?? null) ? $config['periodDays'] : null;
        $channels = null;
        if (is_array($config['channels'] ?? null)) {
            $channels = array_values(array_filter($config['channels'], 'is_string'));
        }

        return ['periodDays' => $periodDays, 'channels' => $channels];
    }

    /**
     * @inheritDoc
     */
    public function save(int $periodDays, array $channels): void
    {
        $userId = $this->userContext->getUserId();
        if ($userId === null) {
            return;
        }

        $config = $this->serializer->serialize(['periodDays' => $periodDays, 'channels' => $channels]);
        if (!is_string($config)) {
            return;
        }

        $bookmark = $this->find() ?? $this->bookmarkFactory->create();
        $bookmark->setUserId((int) $userId)
            ->setNamespace(self::NAMESPACE)
            ->setIdentifier(self::IDENTIFIER)
            ->setTitle('Activity dashboard')
            ->setCurrent(true)
            ->setConfig($config);
        $this->bookmarkRepository->save($bookmark);
    }

    /**
     * The admin user's saved bookmark, or null
     *
     * The lookup returns null when there is none, whatever its docblock says.
     *
     * @return BookmarkInterface|null
     */
    private function find(): ?BookmarkInterface
    {
        /** @var BookmarkInterface|null $bookmark */
        $bookmark = $this->bookmarkManagement->getByIdentifierNamespace(self::IDENTIFIER, self::NAMESPACE);

        return $bookmark;
    }
}
