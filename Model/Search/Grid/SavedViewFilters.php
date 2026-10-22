<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Grid;

/**
 * Removes the grid filters and the keyword search from a saved state of the activity grid
 *
 * The grid shows the page's search and nothing else: it has no column filters and no keyword box, so a filter kept
 * in an admin's saved view would narrow the grid where nobody can see or clear it. A bookmark holds the grid's state
 * under "current" and under each view's "data"; sorting, paging, columns and their order are kept.
 */
class SavedViewFilters
{
    /**
     * State keys of the removed filtering components
     */
    private const FILTER_STATES = ['filters', 'search'];

    /**
     * The bookmark configuration without filters and keyword search
     *
     * @param array<mixed> $config A decoded bookmark configuration
     * @return array<mixed>
     */
    public function withoutFilters(array $config): array
    {
        if (is_array($config['current'] ?? null)) {
            $config['current'] = $this->stateWithout($config['current']);
        }

        if (is_array($config['views'] ?? null)) {
            foreach ($config['views'] as $index => $view) {
                if (is_array($view) && is_array($view['data'] ?? null)) {
                    $view['data'] = $this->stateWithout($view['data']);
                    $config['views'][$index] = $view;
                }
            }
        }

        return $config;
    }

    /**
     * One saved grid state without its filtering components' state
     *
     * @param array<mixed> $state
     * @return array<mixed>
     */
    private function stateWithout(array $state): array
    {
        return array_diff_key($state, array_flip(self::FILTER_STATES));
    }
}
