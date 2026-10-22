<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Export;

use Hryvinskyi\ActivityLog\Model\Export\FormulaGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Text a spreadsheet would read as a formula is written as text; other text is left alone
 */
class FormulaGuardTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function cells(): array
    {
        return [
            'an equals sign' => ['=HYPERLINK("http://x","y")', '\'=HYPERLINK("http://x","y")'],
            'a plus sign' => ['+1+1', "'+1+1"],
            'a minus sign' => ['-2+3', "'-2+3"],
            'an at sign' => ['@SUM(A1)', "'@SUM(A1)"],
            'a tab' => ["\t=1", "'\t=1"],
            'a carriage return' => ["\r=1", "'\r=1"],
            'plain text' => ['Product 24-MB01', 'Product 24-MB01'],
            'a formula character further in' => ['a=1', 'a=1'],
            'a line feed' => ["\n=1", "\n=1"],
            'nothing' => ['', ''],
        ];
    }

    #[DataProvider('cells')]
    public function testWritesTextAFormulaWouldStartWithAsText(string $value, string $cell): void
    {
        self::assertSame($cell, (new FormulaGuard())->cell($value));
    }
}
