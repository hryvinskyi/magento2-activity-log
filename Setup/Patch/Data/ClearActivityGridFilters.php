<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Setup\Patch\Data;

use Hryvinskyi\ActivityLog\Model\Search\Grid\SavedViewFilters;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Clears the filters and keyword search every admin saved in the activity grid's views, once
 *
 * The grid lost its column filters and keyword box: it shows the search page's search. Filters saved before that —
 * a channel from a menu link, a day range, an actor — would otherwise stay applied to the grid, invisible and
 * impossible to remove. Each admin's other choices (columns, sorting, page size) are kept. A bookmark whose
 * configuration cannot be read is left as it is.
 */
class ClearActivityGridFilters implements DataPatchInterface
{
    /**
     * Bookmark namespace of the activity grid
     */
    public const NAMESPACE = 'hryvinskyi_activity_log_listing';

    private const TABLE = 'ui_bookmark';

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param SavedViewFilters $savedViewFilters
     * @param Json $json
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly SavedViewFilters $savedViewFilters,
        private readonly Json $json
    ) {
    }

    /**
     * Rewrite every saved state of the grid without its filters
     *
     * @return $this
     */
    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable(self::TABLE);
        $bookmarks = $connection->fetchPairs(
            $connection->select()
                ->from($table, ['bookmark_id', 'config'])
                ->where('namespace = ?', self::NAMESPACE)
        );

        foreach ($bookmarks as $bookmarkId => $stored) {
            $cleaned = $this->cleaned(is_string($stored) ? $stored : '');
            if ($cleaned !== null) {
                $connection->update($table, ['config' => $cleaned], ['bookmark_id = ?' => (int) $bookmarkId]);
            }
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * The stored configuration without filters, or null when it holds none or cannot be read
     *
     * @param string $stored
     * @return string|null
     */
    private function cleaned(string $stored): ?string
    {
        try {
            $config = $this->json->unserialize($stored);
        } catch (\InvalidArgumentException) {
            return null;
        }
        if (!is_array($config)) {
            return null;
        }

        $withoutFilters = $this->savedViewFilters->withoutFilters($config);

        return $withoutFilters === $config ? null : (string) $this->json->serialize($withoutFilters);
    }
}
