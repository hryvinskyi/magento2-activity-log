<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api;

/**
 * Masks secrets before anything is stored
 *
 * A value under a secret field name is replaced by MASK. A credential with an authorization scheme keeps the
 * scheme ("Bearer ••••••") so people can still tell what kind of credential was sent. JSON text is decoded,
 * masked and encoded again; other values pass through unchanged.
 *
 * @api
 */
interface SanitizerInterface
{
    /**
     * What a masked value is replaced with
     */
    public const MASK = '••••••';

    /**
     * Mask the secrets in a value: an array (keys are field names), JSON text, or a scalar
     *
     * @param mixed $data
     * @return mixed
     */
    public function sanitize(mixed $data): mixed;

    /**
     * Mask one value known to belong to a field
     *
     * @param string $field
     * @param mixed $value
     * @return mixed
     */
    public function sanitizeField(string $field, mixed $value): mixed;

    /**
     * Whether a field name names a secret
     *
     * @param string $field
     * @return bool
     */
    public function isSecretField(string $field): bool;

    /**
     * Whether a stored value is a mask
     *
     * @param mixed $value
     * @return bool
     */
    public function isMasked(mixed $value): bool;
}
