<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Correlation;

use Hryvinskyi\ActivityLog\Model\Correlation\CorrelationScope;
use Hryvinskyi\ActivityLog\Model\Correlation\IdGeneratorInterface;
use Hryvinskyi\ActivityLog\Model\Correlation\IncomingIdInterface;
use PHPUnit\Framework\TestCase;

class CorrelationScopeTest extends TestCase
{
    public function testOneRequestIsOneOperation(): void
    {
        $generator = $this->createMock(IdGeneratorInterface::class);
        $generator->expects(self::once())->method('next')->willReturn('request01');
        $scope = new CorrelationScope($generator, $this->incoming(null));

        $scope->current();

        self::assertSame('request01', $scope->current());
    }

    public function testUsesAWellFormedIdTheCallerSent(): void
    {
        $scope = new CorrelationScope($this->sequence(['generated']), $this->incoming('erp-6f1c1a9b-2a3e'));

        self::assertSame('erp-6f1c1a9b-2a3e', $scope->current());
    }

    public function testIgnoresAMalformedIdTheCallerSent(): void
    {
        $scope = new CorrelationScope($this->sequence(['generated']), $this->incoming("x' OR 1=1 --"));

        self::assertSame('generated', $scope->current());
    }

    public function testNestedOperationsCloseInOrder(): void
    {
        $scope = new CorrelationScope($this->sequence(['request01', 'callA0001', 'callB0001']), $this->incoming(null));

        self::assertSame('request01', $scope->current());
        $first = $scope->begin();
        self::assertSame('callA0001', $scope->current());
        $second = $scope->begin();
        self::assertSame('callB0001', $scope->current());

        $scope->end($second);
        self::assertSame($first, $scope->current());
        $scope->end($first);
        self::assertSame('request01', $scope->current());
    }

    public function testClosingAnOuterOperationClosesWhatWasOpenedInsideIt(): void
    {
        $scope = new CorrelationScope($this->sequence(['request01', 'callA0001', 'callB0001']), $this->incoming(null));
        $scope->current();
        $outer = $scope->begin();
        $scope->begin();

        $scope->end($outer);

        self::assertSame('request01', $scope->current());
    }

    public function testResetStartsAFreshRequest(): void
    {
        $scope = new CorrelationScope($this->sequence(['request01', 'request02']), $this->incoming(null));
        self::assertSame('request01', $scope->current());

        $scope->_resetState();

        self::assertSame('request02', $scope->current());
    }

    /**
     * @param list<string> $ids
     * @return IdGeneratorInterface
     */
    private function sequence(array $ids): IdGeneratorInterface
    {
        $generator = $this->createStub(IdGeneratorInterface::class);
        $generator->method('next')->willReturnOnConsecutiveCalls(...$ids);

        return $generator;
    }

    /**
     * @param string|null $id
     * @return IncomingIdInterface
     */
    private function incoming(?string $id): IncomingIdInterface
    {
        $incoming = $this->createStub(IncomingIdInterface::class);
        $incoming->method('read')->willReturn($id);

        return $incoming;
    }
}
