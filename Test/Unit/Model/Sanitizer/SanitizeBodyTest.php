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
use Hryvinskyi\ActivityLog\Model\Sanitizer\Body\FormBodyMasker;
use Hryvinskyi\ActivityLog\Model\Sanitizer\Body\JsonBodyMasker;
use Hryvinskyi\ActivityLog\Model\Sanitizer\Body\XmlBodyMasker;
use Hryvinskyi\ActivityLog\Model\Sanitizer\Sanitizer;
use Magento\Framework\DomDocument\DomDocumentFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Message bodies of every format, through the sanitizer with its body maskers in their wired order
 */
class SanitizeBodyTest extends TestCase
{
    private const MASK = SanitizerInterface::MASK;

    /**
     * @var Sanitizer
     */
    private Sanitizer $sanitizer;

    protected function setUp(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getSecretFields')->willReturn(['passwd', 'form_key', 'card_number']);
        $this->sanitizer = new Sanitizer(
            $config,
            ['password', 'token', 'secret', 'authorization', 'cookie', 'api_key', 'apikey', 'private_key'],
            [
                'json' => new JsonBodyMasker(),
                'xml' => new XmlBodyMasker(new DomDocumentFactory()),
                'form' => new FormBodyMasker(),
            ]
        );
    }

    public function testMasksSecretsInAJsonBodyAtAnyDepth(): void
    {
        self::assertSame(
            '{"customer":{"email":"a@b.dk","password":"' . self::MASK . '"},'
            . '"items":[{"sku":"24-MB01","card_number":"' . self::MASK . '"}],'
            . '"tokens":["' . self::MASK . '","' . self::MASK . '"],'
            . '"authorization":"Bearer ' . self::MASK . '"}',
            $this->sanitizer->sanitizeBody(
                '{"customer":{"email":"a@b.dk","password":"hunter2"},'
                . '"items":[{"sku":"24-MB01","card_number":"4111"}],'
                . '"tokens":["t1","t2"],"authorization":"Bearer eyJ.secret"}'
            )
        );
    }

    public function testKeepsTheShapeOfASecretObjectWhileMaskingItsValues(): void
    {
        self::assertSame(
            '{"api_key":{"live":"' . self::MASK . '","test":"' . self::MASK . '","none":null}}',
            $this->sanitizer->sanitizeBody('{"api_key":{"live":"k1","test":"k2","none":null}}')
        );
    }

    public function testMasksJsonListsAndObjectsWithNumericMemberNames(): void
    {
        self::assertSame(
            '[{"token":"' . self::MASK . '"},{"sku":"a"}]',
            $this->sanitizer->sanitizeBody('[{"token":"t"},{"sku":"a"}]')
        );
        self::assertSame(
            '{"0":{"password":"' . self::MASK . '"},"1":"a"}',
            $this->sanitizer->sanitizeBody('{"0":{"password":"x"},"1":"a"}')
        );
    }

    public function testKeepsAnEmptyJsonObjectAnObject(): void
    {
        self::assertSame(
            '{"password":"' . self::MASK . '","extension_attributes":{},"items":[]}',
            $this->sanitizer->sanitizeBody('{"password":"x","extension_attributes":{},"items":[]}')
        );
    }

    public function testWritesAMaskedJsonBodyWithSlashesUnicodeAndZeroFractionsAsTheyWere(): void
    {
        self::assertSame(
            '{"url":"https://shop.example/api","name":"Ærø","price":1.0,"token":"' . self::MASK . '"}',
            $this->sanitizer->sanitizeBody(
                '{"url":"https:\/\/shop.example\/api","name":"Ærø","price":1.0,"token":"t"}'
            )
        );
    }

    public function testReturnsAJsonBodyWithoutSecretsByteForByte(): void
    {
        $body = "{ \"sku\" : \"a\\/b\",\n  \"price\": 10.50, \"empty\": {} }";

        self::assertSame($body, $this->sanitizer->sanitizeBody($body));
    }

    public function testLeavesAnEmptySecretInAJsonBodyAsItWas(): void
    {
        $body = '{"password":"","token":null}';

        self::assertSame($body, $this->sanitizer->sanitizeBody($body));
    }

    public function testMasksAMalformedJsonBodyThatNamesASecretWhole(): void
    {
        self::assertSame(self::MASK, $this->sanitizer->sanitizeBody('{"password":"x"'));
        self::assertSame(self::MASK, $this->sanitizer->sanitizeBody('{"password":"x=y"'));
    }

    public function testLeavesAMalformedJsonBodyWithoutSecretNamesAlone(): void
    {
        self::assertSame('{"sku":"a"', $this->sanitizer->sanitizeBody('{"sku":"a"'));
    }

    public function testMasksSecretElementsAndAttributesOfAnXmlBody(): void
    {
        self::assertSame(
            '<customer><email>a@b.dk</email><password>' . self::MASK . '</password>'
            . '<card token="' . self::MASK . '" type="visa">1</card>'
            . '<credentials><api-key>' . self::MASK . '</api-key></credentials></customer>',
            $this->sanitizer->sanitizeBody(
                '<customer><email>a@b.dk</email><password>hunter2</password>'
                . '<card token="t0k3n" type="visa">1</card>'
                . '<credentials><api-key><live>k1</live></api-key></credentials></customer>'
            )
        );
    }

    public function testMatchesNamespacedXmlNamesByTheirLocalName(): void
    {
        self::assertSame(
            '<ns:Login xmlns:ns="urn:shop"><ns:User>jane</ns:User>'
            . '<ns:Password>' . self::MASK . '</ns:Password></ns:Login>',
            $this->sanitizer->sanitizeBody(
                '<ns:Login xmlns:ns="urn:shop"><ns:User>jane</ns:User><ns:Password>x</ns:Password></ns:Login>'
            )
        );
    }

    public function testKeepsTheDeclarationOfADeclaredXmlBody(): void
    {
        $masked = $this->sanitizer->sanitizeBody(
            "<?xml version=\"1.0\"?>\n<request><token>t0k3n</token></request>"
        );

        self::assertStringStartsWith('<?xml version="1.0"', $masked);
        self::assertStringContainsString('<request><token>' . self::MASK . '</token></request>', $masked);
    }

    public function testReturnsAnXmlBodyWithoutSecretsByteForByte(): void
    {
        $body = "  <order id='7'>\n  <sku>24-MB01</sku>\n  <note>call before delivery</note>\n</order>\n";

        self::assertSame($body, $this->sanitizer->sanitizeBody($body));
    }

    public function testNeverLoadsAnExternalEntityOfAnXmlBody(): void
    {
        $secret = tempnam(sys_get_temp_dir(), 'xxe');
        self::assertIsString($secret);
        file_put_contents($secret, 'leaked-file-content');
        $doctype = '<!DOCTYPE order [<!ENTITY x SYSTEM "file://' . $secret . '">]>';

        try {
            $untouched = $doctype . '<order><sku>&x;</sku></order>';
            $masked = $this->sanitizer->sanitizeBody($doctype . '<order><password>p</password><sku>&x;</sku></order>');
        } finally {
            unlink($secret);
        }

        self::assertSame($untouched, $this->sanitizer->sanitizeBody($untouched));
        self::assertStringNotContainsString('leaked-file-content', $masked);
        self::assertStringContainsString('<password>' . self::MASK . '</password>', $masked);
        self::assertStringContainsString('<sku>&x;</sku>', $masked);
    }

    public function testMasksAMalformedXmlBodyThatNamesASecretWhole(): void
    {
        self::assertSame(self::MASK, $this->sanitizer->sanitizeBody('<login><password>x</login>'));
        self::assertSame('<p>hello</b>', $this->sanitizer->sanitizeBody('<p>hello</b>'));
    }

    /**
     * @param string $body
     * @param string $expected
     */
    #[DataProvider('formBodies')]
    public function testMasksSecretValuesOfAFormBodyInPlace(string $body, string $expected): void
    {
        self::assertSame($expected, $this->sanitizer->sanitizeBody($body));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function formBodies(): array
    {
        return [
            'nested key' => ['customer[password]=x&sku=a', 'customer[password]=' . self::MASK . '&sku=a'],
            'encoded key' => [
                'customer%5Bpassword%5D=x&name=Jane+Doe%21',
                'customer%5Bpassword%5D=' . self::MASK . '&name=Jane+Doe%21',
            ],
            'query string' => ['a=1&access_token=x&b=', 'a=1&access_token=' . self::MASK . '&b='],
            'list key' => ['password[]=x&password[]=y', 'password[]=' . self::MASK . '&password[]=' . self::MASK],
            'empty secret' => ['password=&sku=a', 'password=&sku=a'],
            'no secret' => ['searchCriteria[pageSize]=20&q=bag', 'searchCriteria[pageSize]=20&q=bag'],
            'only the last segment names the value' => ['password[hint]=dog&a=1', 'password[hint]=dog&a=1'],
        ];
    }

    public function testMasksPlainTextThatNamesASecretWhole(): void
    {
        self::assertSame(self::MASK, $this->sanitizer->sanitizeBody('Your Api-Key is 12345'));
        self::assertSame(self::MASK, $this->sanitizer->sanitizeBody('reset token: abc'));
    }

    public function testLeavesPlainTextWithoutSecretNamesAlone(): void
    {
        self::assertSame('Product saved.', $this->sanitizer->sanitizeBody('Product saved.'));
        self::assertSame('', $this->sanitizer->sanitizeBody(''));
        self::assertSame('"plain json string"', $this->sanitizer->sanitizeBody('"plain json string"'));
    }
}
