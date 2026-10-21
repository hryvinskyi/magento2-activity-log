<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Icon;

use Hryvinskyi\ActivityLog\Model\Icon\InlineSvg;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * An icon file turned into markup for a page
 */
class InlineSvgTest extends TestCase
{
    private const FILE = <<<'SVG'
<!-- @license lucide-static v0.460.0 - ISC -->
<svg
  class="lucide lucide-trash-2"
  xmlns="http://www.w3.org/2000/svg"
  width="24"
  height="24"
  viewBox="0 0 24 24"
  fill="none"
  stroke="currentColor"
>
  <path d="M3 6h18" />
  <line x1="10" x2="10" y1="11" y2="17" />
</svg>
SVG;

    public function testTheFileBecomesOneLineWithoutItsCommentAndHiddenFromAssistiveTechnology(): void
    {
        self::assertSame(
            '<svg aria-hidden="true" focusable="false" class="lucide lucide-trash-2" '
            . 'xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" '
            . 'stroke="currentColor" ><path d="M3 6h18" /><line x1="10" x2="10" y1="11" y2="17" /></svg>',
            (new InlineSvg())->markup(self::FILE, '')
        );
    }

    public function testThePageClassesComeFirstInTheClassAttribute(): void
    {
        $markup = (new InlineSvg())->markup(self::FILE, ' activity-button__icon is-large ');

        self::assertStringContainsString('class="activity-button__icon is-large lucide lucide-trash-2"', $markup);
        self::assertSame(1, substr_count($markup, 'class="'));
    }

    public function testAFileWithoutAClassAttributeKeepsItsMarkup(): void
    {
        self::assertSame(
            '<svg aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M1 1"/></svg>',
            (new InlineSvg())->markup('<svg viewBox="0 0 24 24"><path d="M1 1"/></svg>', 'x')
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeClasses(): array
    {
        return [
            'quote' => ['a" onload="alert(1)'],
            'angle bracket' => ['a><script>'],
            'ampersand' => ['a&b'],
        ];
    }

    #[DataProvider('unsafeClasses')]
    public function testAClassThatCouldLeaveTheAttributeIsRefused(string $class): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new InlineSvg())->markup(self::FILE, $class);
    }
}
