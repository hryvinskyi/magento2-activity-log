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
 * Form-encoded pairs (`key=value&key=value`, a key may be `a[b][c]`), as in a form body or a query string
 *
 * The value of each pair whose last key segment is a secret name is replaced by the mask in place; every other
 * byte stays as it was. Only text made of encoded pairs is recognised: anything with a quote, a brace, an angle
 * bracket, whitespace or a stray `=` is left to the plain-text rule, which masks the whole body when it names a
 * secret, so a broken document of another format is never half-masked as a form.
 */
class FormBodyMasker implements BodyMaskerInterface
{
    private const PAIR = '[\w.~%+\-\[\]]+=[^&=\s"<>{}]*';

    /**
     * @inheritDoc
     */
    public function mask(string $body, SanitizerInterface $sanitizer): ?string
    {
        if (preg_match('/^' . self::PAIR . '(?:&' . self::PAIR . ')*$/D', $body) !== 1) {
            return null;
        }

        $pairs = [];
        foreach (explode('&', $body) as $pair) {
            [$key, $value] = explode('=', $pair, 2) + [1 => ''];
            $pairs[] = $value !== '' && $sanitizer->isSecretField($this->lastSegment(urldecode($key)))
                ? $key . '=' . SanitizerInterface::MASK
                : $pair;
        }

        return implode('&', $pairs);
    }

    /**
     * The name a value belongs to: `c` of `a[b][c]`, `a` of `a` and of `a[]`
     *
     * @param string $key
     * @return string
     */
    private function lastSegment(string $key): string
    {
        $segments = preg_split('/[\[\]]+/', $key, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $segments === [] ? $key : $segments[count($segments) - 1];
    }
}
