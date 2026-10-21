<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model;

use Hryvinskyi\ActivityLog\Api\SanitizerInterface;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The sanitizer as the container builds it: the mandatory secret names and the body maskers it is given
 */
class SanitizerTest extends TestCase
{
    private const MASK = SanitizerInterface::MASK;

    #[Config('hryvinskyi_activity_log/security/secret_fields', 'X-Partner-Ref')]
    public function testConfigurationAddsNamesButCannotRemoveTheMandatoryOnes(): void
    {
        $sanitizer = $this->sanitizer();

        self::assertTrue($sanitizer->isSecretField('x_partner_ref'));
        foreach (['Password', 'X-Auth-Token', 'Client-Secret', 'Authorization', 'Set-Cookie', 'X-Api-Key',
                     'apikey', 'Private Key'] as $name) {
            self::assertTrue($sanitizer->isSecretField($name), $name);
        }
        self::assertFalse($sanitizer->isSecretField('passwd'));
        self::assertFalse($sanitizer->isSecretField('sku'));
    }

    #[Config('hryvinskyi_activity_log/security/secret_fields', 'partner_ref')]
    public function testEveryBodyFormatIsMasked(): void
    {
        $sanitizer = $this->sanitizer();

        self::assertSame(
            '{"password":"' . self::MASK . '","meta":{}}',
            $sanitizer->sanitizeBody('{"password":"x","meta":{}}')
        );
        self::assertSame(
            '<login><user>jane</user><password>' . self::MASK . '</password></login>',
            $sanitizer->sanitizeBody('<login><user>jane</user><password>x</password></login>')
        );
        self::assertSame(
            'customer[password]=' . self::MASK . '&sku=a',
            $sanitizer->sanitizeBody('customer[password]=x&sku=a')
        );
        self::assertSame(self::MASK, $sanitizer->sanitizeBody('{"password":"x"'));
        self::assertSame('Product saved.', $sanitizer->sanitizeBody('Product saved.'));
    }

    /**
     * A fresh sanitizer, so the configured names are read under this test's fixtures
     *
     * @return SanitizerInterface
     */
    private function sanitizer(): SanitizerInterface
    {
        $sanitizer = Bootstrap::getObjectManager()->create(SanitizerInterface::class);
        self::assertInstanceOf(SanitizerInterface::class, $sanitizer);

        return $sanitizer;
    }
}
