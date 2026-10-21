<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Sanitizer;

use Hryvinskyi\ActivityLog\Api\ConfigInterface;
use Hryvinskyi\ActivityLog\Api\SanitizerInterface;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * Masks values under secret field names, in arrays and in JSON text, to any depth
 */
class Sanitizer implements SanitizerInterface, ResetAfterRequestInterface
{
    private const SCHEME_PATTERN = '/^(Bearer|Basic|Digest|OAuth|Token)\s+\S/i';

    /**
     * @var list<string>|null
     */
    private ?array $secretFields = null;

    /**
     * @param ConfigInterface $config
     */
    public function __construct(
        private readonly ConfigInterface $config
    ) {
    }

    /**
     * @inheritDoc
     */
    public function sanitize(mixed $data): mixed
    {
        if (is_array($data)) {
            return $this->sanitizeArray($data);
        }

        if (is_string($data)) {
            return $this->sanitizeJsonText($data);
        }

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function sanitizeField(string $field, mixed $value): mixed
    {
        if ($this->isSecretField($field)) {
            return $this->mask($value);
        }

        return $this->sanitize($value);
    }

    /**
     * @inheritDoc
     */
    public function isSecretField(string $field): bool
    {
        $name = strtolower($field);
        foreach ($this->secretFields() as $secret) {
            if (str_contains($name, $secret)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function isMasked(mixed $value): bool
    {
        return is_string($value) && str_ends_with($value, self::MASK);
    }

    /**
     * Mask every secret under an array's keys
     *
     * @param array<mixed> $data
     * @return array<mixed>
     */
    private function sanitizeArray(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            $sanitized[$key] = is_string($key) ? $this->sanitizeField($key, $value) : $this->sanitize($value);
        }

        return $sanitized;
    }

    /**
     * Mask secrets inside JSON text; any other text is returned as it is
     *
     * @param string $text
     * @return string
     */
    private function sanitizeJsonText(string $text): string
    {
        $trimmed = ltrim($text);
        if ($trimmed === '' || ($trimmed[0] !== '{' && $trimmed[0] !== '[')) {
            return $text;
        }

        try {
            $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $text;
        }

        if (!is_array($decoded)) {
            return $text;
        }

        $encoded = json_encode(
            $this->sanitizeArray($decoded),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );

        return $encoded === false ? $text : $encoded;
    }

    /**
     * Replace a secret value; a credential keeps its authorization scheme
     *
     * @param mixed $value
     * @return mixed
     */
    private function mask(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->mask($item), $value);
        }

        if (is_string($value) && preg_match(self::SCHEME_PATTERN, $value) === 1) {
            return strtok($value, " \t") . ' ' . self::MASK;
        }

        return self::MASK;
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->secretFields = null;
    }

    /**
     * Secret field names, read once per instance
     *
     * @return list<string>
     */
    private function secretFields(): array
    {
        return $this->secretFields ??= $this->config->getSecretFields();
    }
}
