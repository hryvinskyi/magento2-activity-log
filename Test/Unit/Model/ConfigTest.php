<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model;

use Hryvinskyi\ActivityLog\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testTheSecretListIsSplitOnCommasAndLineBreaksInLowerCaseOnce(): void
    {
        self::assertSame(
            ['password', 'x_api_key', 'token'],
            $this->config(["Password, X_API_KEY\ntoken,,password"])->getSecretFields()
        );
    }

    public function testNoStoredListIsAnEmptyList(): void
    {
        self::assertSame([], $this->config([null])->getSecretFields());
    }

    public function testAChangedStoredListIsReadAsItIsNow(): void
    {
        $config = $this->config(['password', 'password', 'password,signature']);
        $first = $config->getSecretFields();
        $again = $config->getSecretFields();
        $changed = $config->getSecretFields();

        self::assertSame([['password'], ['password'], ['password', 'signature']], [$first, $again, $changed]);
    }

    /**
     * Settings whose stored secret list is each of these values, one per read
     *
     * @param list<string|null> $stored
     * @return Config
     */
    private function config(array $stored): Config
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnOnConsecutiveCalls(...$stored);

        return new Config($scopeConfig);
    }
}
