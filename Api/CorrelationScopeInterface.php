<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api;

/**
 * The operation activities happening now belong to
 *
 * One HTTP request is one operation unless something opens a narrower one. A long-running process that serves many
 * operations (a stdio server, a queue consumer) opens a scope per operation and closes it when the operation ends.
 *
 * @api
 */
interface CorrelationScopeInterface
{
    /**
     * Correlation id of the innermost open operation, or of the request when none is open
     *
     * @return string
     */
    public function current(): string;

    /**
     * Open a nested operation and return its correlation id
     *
     * @return string
     */
    public function begin(): string;

    /**
     * Close the operation opened with that id, and every operation opened inside it
     *
     * @param string $correlationId
     * @return void
     */
    public function end(string $correlationId): void;
}
