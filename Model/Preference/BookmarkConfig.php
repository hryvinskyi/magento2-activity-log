<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Preference;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Ui\Api\BookmarkManagementInterface;
use Magento\Ui\Api\BookmarkRepositoryInterface;
use Magento\Ui\Api\Data\BookmarkInterface;
use Magento\Ui\Api\Data\BookmarkInterfaceFactory;

/**
 * The current admin user's remembered choices on one page, kept in the UI bookmarks where Magento keeps grid views
 *
 * Each page keeps its choices under a namespace of its own, so no page reads another's. Nothing is kept for a
 * request without a signed-in admin user.
 *
 * A save replaces the one bookmark the page keeps for the admin user, or adds it when there is none: two saves at
 * once (two quick clicks) are taken one after the other under a lock per admin user and page, so they never add two
 * bookmarks; a second one left behind by an earlier race is removed by the next save. The bookmarks table has no
 * unique key to lean on.
 */
class BookmarkConfig
{
    /**
     * Identifier of the one bookmark a page keeps per admin user
     */
    private const IDENTIFIER = 'current';

    /**
     * Prefix of the lock a save holds, followed by the admin user and the page
     */
    private const LOCK = 'hryvinskyi_activity_log_preference_';

    /**
     * How long a save waits for another save of the same choices, in seconds
     */
    private const LOCK_SECONDS = 10;

    /**
     * @param BookmarkManagementInterface $bookmarkManagement
     * @param BookmarkRepositoryInterface $bookmarkRepository
     * @param BookmarkInterfaceFactory $bookmarkFactory
     * @param UserContextInterface $userContext
     * @param SerializerInterface $serializer
     * @param LockManagerInterface $lockManager
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        private readonly BookmarkManagementInterface $bookmarkManagement,
        private readonly BookmarkRepositoryInterface $bookmarkRepository,
        private readonly BookmarkInterfaceFactory $bookmarkFactory,
        private readonly UserContextInterface $userContext,
        private readonly SerializerInterface $serializer,
        private readonly LockManagerInterface $lockManager,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    /**
     * The choices kept under a namespace, empty when none are
     *
     * @param string $namespace
     * @return array<mixed>
     */
    public function load(string $namespace): array
    {
        return $this->find($namespace)?->getConfig() ?? [];
    }

    /**
     * Keep choices under a namespace, replacing what was kept there
     *
     * @param string $namespace
     * @param string $title What the bookmark is, for whoever reads the table
     * @param array<string,mixed> $config
     * @return void
     * @throws CouldNotSaveException When another save of the same choices does not finish in time, or saving fails
     */
    public function save(string $namespace, string $title, array $config): void
    {
        $userId = $this->userContext->getUserId();
        if ($userId === null) {
            return;
        }

        $serialized = $this->serializer->serialize($config);
        if (!is_string($serialized)) {
            return;
        }

        $lock = self::LOCK . (int) $userId . '_' . $namespace;
        if (!$this->lockManager->lock($lock, self::LOCK_SECONDS)) {
            throw new CouldNotSaveException(__('Your choices could not be kept. Try again.'));
        }

        try {
            $kept = $this->kept((int) $userId, $namespace);
            $bookmark = array_shift($kept) ?? $this->bookmarkFactory->create();
            foreach ($kept as $duplicate) {
                $this->bookmarkRepository->delete($duplicate);
            }

            $bookmark->setUserId((int) $userId)
                ->setNamespace($namespace)
                ->setIdentifier(self::IDENTIFIER)
                ->setTitle($title)
                ->setCurrent(true)
                ->setConfig($serialized);
            $this->bookmarkRepository->save($bookmark);
        } finally {
            $this->lockManager->unlock($lock);
        }
    }

    /**
     * The admin user's bookmarks of a namespace that keep its choices, the oldest first
     *
     * @param int $userId
     * @param string $namespace
     * @return list<BookmarkInterface>
     */
    private function kept(int $userId, string $namespace): array
    {
        $criteria = $this->searchCriteriaBuilder
            ->addFilter('user_id', $userId)
            ->addFilter('namespace', $namespace)
            ->addFilter('identifier', self::IDENTIFIER)
            ->create();
        $kept = array_values($this->bookmarkRepository->getList($criteria)->getItems());
        usort(
            $kept,
            static fn (BookmarkInterface $one, BookmarkInterface $other): int
                => (int) $one->getId() <=> (int) $other->getId()
        );

        return $kept;
    }

    /**
     * The admin user's bookmark of a namespace, or null
     *
     * The lookup returns null when there is none, whatever its docblock says.
     *
     * @param string $namespace
     * @return BookmarkInterface|null
     */
    private function find(string $namespace): ?BookmarkInterface
    {
        /** @var BookmarkInterface|null $bookmark */
        $bookmark = $this->bookmarkManagement->getByIdentifierNamespace(self::IDENTIFIER, $namespace);

        return $bookmark;
    }
}
