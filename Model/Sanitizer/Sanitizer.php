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
use Hryvinskyi\ActivityLog\Model\Sanitizer\Body\BodyMaskerInterface;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * Masks values under secret field names, in arrays and in JSON text, to any depth, and secrets in message bodies
 *
 * The secret names are the configured list plus a floor of names configuration cannot remove. Names on both sides
 * are compared normalised: lower case, hyphens and spaces read as underscores. The configured list is asked for on
 * every check, so a list changed and reloaded within the process (a setup patch merging names into it) is followed at
 * once; it is normalised again only when it differs from the one read before.
 */
class Sanitizer implements SanitizerInterface, ResetAfterRequestInterface
{
    private const SCHEME_PATTERN = '/^(Bearer|Basic|Digest|OAuth|Token)\s+\S/i';

    /**
     * The configured list the normalised names were made from; null before the first check
     *
     * @var list<string>|null
     */
    private ?array $configuredFields = null;

    /**
     * @var list<string>
     */
    private array $secretFields = [];

    /**
     * @var list<BodyMaskerInterface>
     */
    private readonly array $bodyMaskers;

    /**
     * @param ConfigInterface $config
     * @param array<string> $mandatorySecretFields Names that are secret whatever the configuration says
     * @param array<mixed> $bodyMaskers A BodyMaskerInterface per body format, asked in this order
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly array $mandatorySecretFields = [],
        array $bodyMaskers = []
    ) {
        $maskers = [];
        foreach ($bodyMaskers as $code => $masker) {
            if (!$masker instanceof BodyMaskerInterface) {
                throw new \InvalidArgumentException(
                    sprintf('Body masker "%s" must implement %s.', $code, BodyMaskerInterface::class)
                );
            }
            $maskers[] = $masker;
        }
        $this->bodyMaskers = $maskers;
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
    public function sanitizeBody(string $body): string
    {
        foreach ($this->bodyMaskers as $masker) {
            $masked = $masker->mask($body, $this);
            if ($masked !== null) {
                return $masked;
            }
        }

        return $this->isSecretField($body) ? self::MASK : $body;
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
        $name = $this->normalise($field);
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
        $this->configuredFields = null;
        $this->secretFields = [];
    }

    /**
     * Secret field names, normalised: the mandatory ones and the configured ones as configured now
     *
     * @return list<string>
     */
    private function secretFields(): array
    {
        $configured = $this->config->getSecretFields();
        if ($configured === $this->configuredFields) {
            return $this->secretFields;
        }

        $fields = [];
        foreach (array_merge(array_values($this->mandatorySecretFields), $configured) as $field) {
            $field = $this->normalise($field);
            if ($field !== '') {
                $fields[$field] = $field;
            }
        }

        $this->configuredFields = $configured;

        return $this->secretFields = array_values($fields);
    }

    /**
     * A name as names are compared: lower case, hyphens and spaces read as underscores
     *
     * @param string $name
     * @return string
     */
    private function normalise(string $name): string
    {
        return str_replace(['-', ' '], '_', strtolower($name));
    }
}
