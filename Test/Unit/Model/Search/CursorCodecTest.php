<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCursorInterfaceFactory;
use Hryvinskyi\ActivityLog\Model\Data\Search\SearchCursor;
use Hryvinskyi\ActivityLog\Model\Search\CursorCodec;
use Magento\Framework\Url\DecoderInterface;
use Magento\Framework\Url\Encoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A page position survives its round trip as a token, and an altered token is refused
 */
class CursorCodecTest extends TestCase
{
    public function testATokenDecodesToThePositionItWasMadeFrom(): void
    {
        $codec = $this->codec();
        $position = (new SearchCursor())->withPosition(1_792_500_000_123, 98_765);

        $token = $codec->encode($position);
        $decoded = $codec->decode($token);

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_~-]+$/', $token, 'URL-safe');
        self::assertSame([1_792_500_000_123, 98_765], [$decoded->getOccurredAtMs(), $decoded->getActivityId()]);
    }

    public function testRefusesATokenWithOneCharacterChanged(): void
    {
        $codec = $this->codec();
        $token = $codec->encode((new SearchCursor())->withPosition(1_792_500_000_123, 98_765));
        $tampered = substr($token, 0, 3) . ($token[3] === 'A' ? 'B' : 'A') . substr($token, 4);

        $this->expectException(\InvalidArgumentException::class);
        $codec->decode($tampered);
    }

    public function testRefusesAWellFormedPositionWithoutItsChecksum(): void
    {
        $forged = rtrim(strtr(base64_encode('1792500000123.98764.00000000'), '+/', '-_'), '=');

        $this->expectException(\InvalidArgumentException::class);
        $this->codec()->decode($forged);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function garbage(): array
    {
        return [
            'not base64' => ['%%%'],
            'base64 of something else' => [base64_encode('hello world')],
            'empty' => [''],
            'too long' => [str_repeat('A', 65)],
        ];
    }

    #[DataProvider('garbage')]
    public function testRefusesGarbage(string $token): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->codec()->decode($token);
    }

    /**
     * A codec whose factory hands out blank cursors
     *
     * @return CursorCodec
     */
    private function codec(): CursorCodec
    {
        $factory = $this->createMock(SearchCursorInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(static fn (): SearchCursor => new SearchCursor());

        $decoder = $this->createMock(DecoderInterface::class);
        $decoder->method('decode')->willReturnCallback(
            static fn (string $token): string => (string) base64_decode(strtr($token, '-_~', '+/='))
        );

        return new CursorCodec($factory, new Encoder(), $decoder);
    }
}
