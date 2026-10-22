<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search;

use Hryvinskyi\ActivityLog\Api\Search\SearchExampleInterface;

/**
 * Example searches offered while the log page has nothing to show
 *
 * The module that serves an example's keys adds it to the "examples" argument of this type, ordered by sortOrder.
 * The journal ships none: an example built from its keys alone would have to name another module's data.
 *
 * @api
 */
class ExamplePool
{
    /**
     * @var list<SearchExampleInterface>
     */
    private array $examples = [];

    /**
     * @param array<string,SearchExampleInterface> $examples
     */
    public function __construct(array $examples = [])
    {
        foreach ($examples as $example) {
            $this->add($example);
        }
    }

    /**
     * Every example, in registration order
     *
     * @return list<SearchExampleInterface>
     */
    public function all(): array
    {
        return $this->examples;
    }

    /**
     * Register an example; anything else in the argument fails here, at construction
     *
     * @param SearchExampleInterface $example
     * @return void
     */
    private function add(SearchExampleInterface $example): void
    {
        $this->examples[] = $example;
    }
}
