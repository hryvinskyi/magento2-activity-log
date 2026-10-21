<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Sanitizer;

use Hryvinskyi\ActivityLog\Api\ConfigInterface;
use Hryvinskyi\ActivityLog\Api\SanitizerInterface;
use Hryvinskyi\ActivityLog\Model\Sanitizer\Sanitizer;
use PHPUnit\Framework\TestCase;

class SanitizerTest extends TestCase
{
    /**
     * @var Sanitizer
     */
    private Sanitizer $sanitizer;

    protected function setUp(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getSecretFields')->willReturn(['password', 'token', 'authorization', 'form_key']);
        $this->sanitizer = new Sanitizer($config);
    }

    public function testMasksSecretsAtAnyDepthAndKeepsTheRest(): void
    {
        $sanitized = $this->sanitizer->sanitize([
            'username' => 'jane.doe',
            'password' => 'hunter2',
            'customer' => ['email' => 'john@example.com', 'resetPasswordToken' => 'abc123'],
            'items' => [['sku' => '24-MB01']],
        ]);

        self::assertSame([
            'username' => 'jane.doe',
            'password' => SanitizerInterface::MASK,
            'customer' => ['email' => 'john@example.com', 'resetPasswordToken' => SanitizerInterface::MASK],
            'items' => [['sku' => '24-MB01']],
        ], $sanitized);
    }

    public function testMasksInsideJsonText(): void
    {
        $sanitized = $this->sanitizer->sanitize('{"product":{"sku":"ERP-55012"},"access_token":"t0k3n"}');

        self::assertIsString($sanitized);
        self::assertSame(
            ['product' => ['sku' => 'ERP-55012'], 'access_token' => SanitizerInterface::MASK],
            json_decode($sanitized, true)
        );
    }

    public function testKeepsTheAuthorizationSchemeOfACredential(): void
    {
        self::assertSame(
            'Bearer ' . SanitizerInterface::MASK,
            $this->sanitizer->sanitizeField('Authorization', 'Bearer eyJhbGciOi.secret')
        );
    }

    public function testLeavesTextThatIsNotJsonAlone(): void
    {
        self::assertSame('{not json', $this->sanitizer->sanitize('{not json'));
        self::assertSame('plain text', $this->sanitizer->sanitize('plain text'));
    }

    public function testLeavesAnEmptySecretEmpty(): void
    {
        self::assertSame('', $this->sanitizer->sanitizeField('password', ''));
        self::assertNull($this->sanitizer->sanitizeField('password', null));
    }

    public function testRecognisesAMask(): void
    {
        self::assertTrue($this->sanitizer->isMasked(SanitizerInterface::MASK));
        self::assertTrue($this->sanitizer->isMasked('Bearer ' . SanitizerInterface::MASK));
        self::assertFalse($this->sanitizer->isMasked('jane.doe'));
        self::assertFalse($this->sanitizer->isMasked(42));
    }

    public function testMatchesSecretFieldNamesWithoutRegardToCase(): void
    {
        self::assertTrue($this->sanitizer->isSecretField('X-Auth-TOKEN'));
        self::assertFalse($this->sanitizer->isSecretField('sku'));
    }
}
