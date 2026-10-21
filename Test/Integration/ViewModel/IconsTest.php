<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\ViewModel;

use Hryvinskyi\ActivityLog\Api\IconsInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Icons read from the module that ships them, through the real container and view-file fallback
 */
#[AppArea('adminhtml')]
class IconsTest extends TestCase
{
    public function testAnIconRendersAsDecorativeInlineSvgWithThePageClass(): void
    {
        $markup = $this->icons()->render('Hryvinskyi_ActivityLog::trash-2', 'activity-button__icon');

        self::assertStringStartsWith('<svg aria-hidden="true" focusable="false" ', $markup);
        self::assertStringContainsString('class="activity-button__icon lucide lucide-trash-2"', $markup);
        self::assertStringEndsWith('</svg>', $markup);
        self::assertStringNotContainsString('<!--', $markup);
        self::assertStringNotContainsString("\n", $markup);
    }

    public function testTheSameIconRendersAgainWithAnotherClass(): void
    {
        $icons = $this->icons();
        $icons->render('Hryvinskyi_ActivityLog::list', 'first');

        self::assertStringContainsString(
            'class="second lucide lucide-list"',
            $icons->render('Hryvinskyi_ActivityLog::list', 'second')
        );
    }

    public function testAnIdThatIsNotAModuleAndANameIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->icons()->render('../../etc/env', '');
    }

    public function testAnIconTheModuleDoesNotShipFailsLoudly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No module ships the icon "Hryvinskyi_ActivityLog::no-such-icon".');

        $this->icons()->render('Hryvinskyi_ActivityLog::no-such-icon', '');
    }

    /**
     * @return IconsInterface
     */
    private function icons(): IconsInterface
    {
        $icons = Bootstrap::getObjectManager()->create(IconsInterface::class);
        self::assertInstanceOf(IconsInterface::class, $icons);

        return $icons;
    }
}
