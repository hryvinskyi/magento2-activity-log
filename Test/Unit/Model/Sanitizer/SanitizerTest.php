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
use Hryvinskyi\ActivityLog\Model\Sanitizer\Body\JsonBodyMasker;
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

    public function testReadsHyphensAndSpacesInANameAsUnderscores(): void
    {
        $sanitizer = $this->sanitizer(['api_key', 'set_cookie', 'X-Partner Ref']);

        self::assertTrue($sanitizer->isSecretField('X-Api-Key'));
        self::assertTrue($sanitizer->isSecretField('Api Key'));
        self::assertTrue($sanitizer->isSecretField('Set-Cookie'));
        self::assertTrue($sanitizer->isSecretField('x_partner_ref'));
        self::assertFalse($sanitizer->isSecretField('X-Api-Version'));
    }

    public function testMandatoryNamesStaySecretWhateverTheConfigurationHolds(): void
    {
        $sanitizer = $this->sanitizer(['sku'], ['password', 'cookie', 'api_key']);

        self::assertTrue($sanitizer->isSecretField('Set-Cookie'));
        self::assertTrue($sanitizer->isSecretField('Cookie'));
        self::assertTrue($sanitizer->isSecretField('X-Api-Key'));
        self::assertTrue($sanitizer->isSecretField('customer_password'));
        self::assertTrue($sanitizer->isSecretField('sku'));
        self::assertFalse($sanitizer->isSecretField('email'));
        self::assertFalse($this->sanitizer([])->isSecretField('Set-Cookie'));
    }

    public function testHeaderNamesAreMaskedWhenTheyNameASecret(): void
    {
        $sanitized = $this->sanitizer(['token'], ['cookie', 'api_key'])->sanitize([
            'X-Api-Key' => 'k3y',
            'Set-Cookie' => 'PHPSESSID=abc',
            'Accept' => 'application/json',
        ]);

        self::assertSame([
            'X-Api-Key' => SanitizerInterface::MASK,
            'Set-Cookie' => SanitizerInterface::MASK,
            'Accept' => 'application/json',
        ], $sanitized);
    }

    public function testSanitizeKeepsPlainTextThatNamesASecret(): void
    {
        self::assertSame('my password is hunter2', $this->sanitizer->sanitize('my password is hunter2'));
        self::assertSame('password=hunter2', $this->sanitizer->sanitize('password=hunter2'));
    }

    public function testAListChangedWithinTheProcessIsFollowedAtOnce(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getSecretFields')->willReturnOnConsecutiveCalls(['password'], ['password', 'x_signature']);
        $sanitizer = new Sanitizer($config);

        self::assertFalse($sanitizer->isSecretField('X-Signature'), 'The list before the change');
        self::assertTrue(
            $sanitizer->isSecretField('X-Signature'),
            'A setup patch merges names into the list and reloads the configuration; the next check masks with it'
        );
    }

    public function testConfigurationReloadedForTheNextRequestIsReadAgain(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getSecretFields')->willReturnOnConsecutiveCalls(['token'], ['token'], []);
        $sanitizer = new Sanitizer($config);

        self::assertTrue($sanitizer->isSecretField('access_token'));
        self::assertTrue($sanitizer->isSecretField('access_token'));
        $sanitizer->_resetState();
        self::assertFalse($sanitizer->isSecretField('access_token'));
    }

    public function testRejectsABodyMaskerOfTheWrongType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"broken"');

        new Sanitizer($this->createStub(ConfigInterface::class), [], [
            'json' => new JsonBodyMasker(),
            'broken' => new \stdClass(),
        ]);
    }

    /**
     * A sanitizer reading these configured names, plus these mandatory ones
     *
     * @param list<string> $configured
     * @param list<string> $mandatory
     * @return Sanitizer
     */
    private function sanitizer(array $configured, array $mandatory = []): Sanitizer
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getSecretFields')->willReturn($configured);

        return new Sanitizer($config, $mandatory);
    }
}
