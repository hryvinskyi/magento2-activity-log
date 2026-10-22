<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Export;

/**
 * Keeps recorded text from being read as a formula when an exported file is opened in a spreadsheet
 *
 * A value that starts with "=", "+", "-", "@", a tab or a carriage return is written with a leading apostrophe, which
 * spreadsheets take as "this cell is text". Recorded text comes from people and clients (a subject name, a header, a
 * field value), so an export must not let it run as a formula on the reader's machine. Anything else is unchanged.
 */
class FormulaGuard
{
    /**
     * Characters a spreadsheet reads as the start of a formula
     */
    private const FORMULA_STARTS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * The value as it is safe to write into a cell
     *
     * @param string $value
     * @return string
     */
    public function cell(string $value): string
    {
        return $value !== '' && in_array($value[0], self::FORMULA_STARTS, true) ? "'" . $value : $value;
    }
}
