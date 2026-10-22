<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Search\Fake;

use Hryvinskyi\ActivityLog\Api\Search\SuggestionProviderInterface;
use Hryvinskyi\ActivityLog\Model\Data\Search\Suggestion;

/**
 * A provider of a fixed list of values, counting how often it is asked; it fails when told to
 */
class ListedSuggestions implements SuggestionProviderInterface
{
    /**
     * @var int
     */
    public int $calls = 0;

    /**
     * @param string $key
     * @param list<array{0: string, 1?: string, 2?: string, 3?: int|null}> $values Value, label, hint, count
     * @param bool $failing
     */
    public function __construct(
        private readonly string $key,
        private readonly array $values,
        private readonly bool $failing = false
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @inheritDoc
     */
    public function suggestions(): array
    {
        $this->calls++;
        if ($this->failing) {
            throw new \RuntimeException('The provider is down.');
        }

        $suggestions = [];
        foreach ($this->values as $value) {
            $suggestions[] = (new Suggestion())->withValue($value[0], $value[1] ?? null)
                ->withHint($value[2] ?? '')
                ->withCount($value[3] ?? null);
        }

        return $suggestions;
    }
}
