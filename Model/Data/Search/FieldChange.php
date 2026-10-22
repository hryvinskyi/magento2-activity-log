<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Data\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\FieldChangeInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\ValueKind;

/**
 * Immutable field row, created empty through FieldChangeInterfaceFactory and filled through its with…() methods
 */
class FieldChange implements FieldChangeInterface
{
    private const ENCODING = 'UTF-8';

    /**
     * @var string
     */
    private string $name = '';

    /**
     * @var string|null
     */
    private ?string $label = null;

    /**
     * @var string|null
     */
    private ?string $scope = null;

    /**
     * @var string|null
     */
    private ?string $before = null;

    /**
     * @var string|null
     */
    private ?string $after = null;

    /**
     * @var int
     */
    private int $beforeLength = 0;

    /**
     * @var int
     */
    private int $afterLength = 0;

    /**
     * @var ValueKind
     */
    private ValueKind $kind = ValueKind::Short;

    /**
     * @var bool
     */
    private bool $changed = true;

    /**
     * @var bool
     */
    private bool $automatic = false;

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return $this->label ?? $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getScope(): ?string
    {
        return $this->scope;
    }

    /**
     * @inheritDoc
     */
    public function getBefore(): ?string
    {
        return $this->before;
    }

    /**
     * @inheritDoc
     */
    public function getAfter(): ?string
    {
        return $this->after;
    }

    /**
     * @inheritDoc
     */
    public function getBeforeLength(): int
    {
        return $this->beforeLength;
    }

    /**
     * @inheritDoc
     */
    public function getAfterLength(): int
    {
        return $this->afterLength;
    }

    /**
     * @inheritDoc
     */
    public function isTruncated(): bool
    {
        return $this->beforeLength > self::VALUE_LIMIT || $this->afterLength > self::VALUE_LIMIT;
    }

    /**
     * @inheritDoc
     */
    public function getKind(): ValueKind
    {
        return $this->isTruncated() && $this->kind !== ValueKind::Masked ? ValueKind::Long : $this->kind;
    }

    /**
     * @inheritDoc
     */
    public function isMasked(): bool
    {
        return $this->kind === ValueKind::Masked;
    }

    /**
     * @inheritDoc
     */
    public function isChanged(): bool
    {
        return $this->changed;
    }

    /**
     * @inheritDoc
     */
    public function isAutomatic(): bool
    {
        return $this->changed && $this->automatic;
    }

    /**
     * @inheritDoc
     */
    public function withField(string $name, ?string $label = null): FieldChangeInterface
    {
        if ($name === '') {
            throw new \InvalidArgumentException('A changed field needs its code.');
        }

        $copy = clone $this;
        $copy->name = $name;
        $copy->label = $label === null || $label === '' ? null : $label;

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withScope(?string $scope): FieldChangeInterface
    {
        $copy = clone $this;
        $copy->scope = $scope === '' ? null : $scope;

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withValues(?string $before, ?string $after): FieldChangeInterface
    {
        $copy = clone $this;
        $copy->beforeLength = $this->length($before);
        $copy->afterLength = $this->length($after);
        $copy->before = $this->capped($before);
        $copy->after = $this->capped($after);

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withKind(ValueKind $kind): FieldChangeInterface
    {
        $copy = clone $this;
        $copy->kind = $kind;

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withChanged(bool $changed): FieldChangeInterface
    {
        $copy = clone $this;
        $copy->changed = $changed;

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withAutomatic(bool $automatic): FieldChangeInterface
    {
        $copy = clone $this;
        $copy->automatic = $automatic;

        return $copy;
    }

    /**
     * Length of a value in characters
     *
     * @param string|null $value
     * @return int
     */
    private function length(?string $value): int
    {
        return $value === null ? 0 : mb_strlen($value, self::ENCODING);
    }

    /**
     * A value cut to the transport limit on a character boundary
     *
     * @param string|null $value
     * @return string|null
     */
    private function capped(?string $value): ?string
    {
        if ($value === null || $this->length($value) <= self::VALUE_LIMIT) {
            return $value;
        }

        return mb_substr($value, 0, self::VALUE_LIMIT, self::ENCODING);
    }
}
