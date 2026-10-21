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
 * A JSON object or array: values under secret names are masked, the rest of the document keeps its shape
 *
 * Objects are decoded as objects so an empty object is written back as `{}`, not `[]`. A body without a secret
 * is returned as it came, byte for byte.
 */
class JsonBodyMasker implements BodyMaskerInterface
{
    private const ENCODING = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;

    /**
     * @inheritDoc
     */
    public function mask(string $body, SanitizerInterface $sanitizer): ?string
    {
        $trimmed = ltrim($body);
        if (!str_starts_with($trimmed, '{') && !str_starts_with($trimmed, '[')) {
            return null;
        }

        try {
            $document = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $changed = false;
        $masked = $this->walk($document, $sanitizer, $changed);
        if (!$changed) {
            return $body;
        }

        try {
            return json_encode($masked, self::ENCODING | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return SanitizerInterface::MASK;
        }
    }

    /**
     * Mask the values under secret names, at any depth
     *
     * @param mixed $value
     * @param SanitizerInterface $sanitizer
     * @param bool $changed Set to true when anything was masked
     * @return mixed
     */
    private function walk(mixed $value, SanitizerInterface $sanitizer, bool &$changed): mixed
    {
        if (is_array($value)) {
            foreach ($value as $index => $item) {
                $value[$index] = $this->walk($item, $sanitizer, $changed);
            }

            return $value;
        }

        if (!$value instanceof \stdClass) {
            return $value;
        }

        // A numeric member name ("0") comes back as an integer key
        foreach (get_object_vars($value) as $name => $item) {
            $field = (string) $name;
            $value->{$field} = $sanitizer->isSecretField($field)
                ? $this->maskSecret($field, $item, $sanitizer, $changed)
                : $this->walk($item, $sanitizer, $changed);
        }

        return $value;
    }

    /**
     * Mask every value inside a secret one, keeping the shape of objects and arrays
     *
     * @param string $field
     * @param mixed $value
     * @param SanitizerInterface $sanitizer
     * @param bool $changed Set to true when anything was masked
     * @return mixed
     */
    private function maskSecret(string $field, mixed $value, SanitizerInterface $sanitizer, bool &$changed): mixed
    {
        if (is_array($value)) {
            foreach ($value as $index => $item) {
                $value[$index] = $this->maskSecret($field, $item, $sanitizer, $changed);
            }

            return $value;
        }

        if ($value instanceof \stdClass) {
            foreach (get_object_vars($value) as $name => $item) {
                $value->{$name} = $this->maskSecret($field, $item, $sanitizer, $changed);
            }

            return $value;
        }

        $masked = $sanitizer->sanitizeField($field, $value);
        if ($masked !== $value) {
            $changed = true;
        }

        return $masked;
    }
}
