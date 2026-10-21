<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Sanitizer\Body;

use Hryvinskyi\ActivityLog\Api\SanitizerInterface;

/**
 * Masks the secrets of a message body written in one format
 *
 * The sanitizer asks its maskers in order and the first one that recognises the body answers; a body no masker
 * recognises is treated as plain text by the sanitizer itself.
 */
interface BodyMaskerInterface
{
    /**
     * Mask the secrets of a body, if it is written in this format
     *
     * @param string $body
     * @param SanitizerInterface $sanitizer Decides which names are secret and how a secret value is masked
     * @return string|null The body with its secrets masked (the original text when nothing was secret),
     *                     or null when the body is not written in this format
     */
    public function mask(string $body, SanitizerInterface $sanitizer): ?string;
}
