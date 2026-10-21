<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Data;

/**
 * What an activity acted on, e.g. type "product", id "1245", label "Product #1245 · SKU 24-MB01"
 *
 * @api
 */
interface SubjectInterface
{
    /**
     * Stable kind of the subject, lowercase: "product", "order", "config_value"
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Identifier of the subject within its kind, or null when it has none
     *
     * @return string|null
     */
    public function getId(): ?string;

    /**
     * Text shown to people
     *
     * @return string
     */
    public function getLabel(): string;
}
