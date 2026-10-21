<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Data;

/**
 * How the dashboard draws a widget, and so which data shape the widget returns
 *
 * - number: {cards: list<{label: string, value: string, unit: string}>}
 * - line, bar: {labels: list<string>,
 *   datasets: list<{label: string, color: string|list<string>, values: list<float|int>}>,
 *   stacked?: bool, horizontal?: bool, unit?: string}
 * - table: {columns: list<{key: string, label: string, align?: "left"|"right"}>,
 *   rows: list<{cells: array<string, string>, href?: string, swatch?: string}>}
 *
 * Any shape may add footer: {label: string, url: string}.
 *
 * @api
 */
enum WidgetViewType: string
{
    case Number = 'number';
    case Line = 'line';
    case Bar = 'bar';
    case Table = 'table';
}
