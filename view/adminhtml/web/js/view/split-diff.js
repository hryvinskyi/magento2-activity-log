/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Draws a split diff (what model/split-diff's split() returns) as a table, and opens its folds
 *
 * Four columns: the line number and the text before, the line number and the text after. A changed word is a <del>
 * on the before side and an <ins> on the after side; a changed line with no marked word has its whole text in one
 * <del>/<ins> with the line class, so a change is never told by colour alone. A side with nothing in it carries its
 * name as visually hidden text. Each fold is a <tbody> of its own: the fold's row with its button, then the rows it
 * hides. Stored text is untrusted markup, so every text goes in through textContent. The wording comes from the
 * caller. A DOM adapter: it takes the document to build with and touches nothing else.
 */
define([
    'Hryvinskyi_ActivityLog/js/search/wording'
], function (wording) {
    'use strict';

    var COLUMNS = 4,
        MARKS = Object.freeze({before: 'del', after: 'ins'});

    /**
     * @param {Document} doc
     * @param {string} tag
     * @param {string} className
     * @param {string=} text
     * @returns {HTMLElement}
     */
    function element(doc, tag, className, text) {
        var node = doc.createElement(tag);

        if (className !== '') {
            node.className = className;
        }
        if (text !== undefined) {
            node.textContent = text;
        }

        return node;
    }

    /**
     * @param {{op: string, parts: Array<{text: string, changed: boolean}>}} side
     * @returns {boolean} Whether the side is a changed line none of whose parts is marked
     */
    function isWholeLine(side) {
        return side.op !== 'equal' && !side.parts.some(function (part) {
            return part.changed;
        });
    }

    /**
     * Fills a side's text cell: unchanged parts as <span>s, changed ones in the side's mark
     *
     * @param {Document} doc
     * @param {HTMLElement} cell
     * @param {{op: string, parts: Array<{text: string, changed: boolean}>}} side
     * @param {string} mark "del" or "ins"
     */
    function fillCode(doc, cell, side, mark) {
        if (isWholeLine(side)) {
            cell.appendChild(element(doc, mark, 'split-diff__line', side.parts.map(function (part) {
                return part.text;
            }).join('')));

            return;
        }
        side.parts.forEach(function (part) {
            cell.appendChild(part.changed
                ? element(doc, mark, 'split-diff__word', part.text)
                : element(doc, 'span', '', part.text));
        });
    }

    /**
     * Appends one side of a row: its number cell and its text cell
     *
     * @param {Document} doc
     * @param {HTMLElement} row
     * @param {?{number: number, op: string, parts: Array<{text: string, changed: boolean}>}} side
     * @param {string} position "before" or "after"
     * @param {string} filler The name of a side with nothing in it
     */
    function appendSide(doc, row, side, position, filler) {
        var modifier = side === null ? 'empty' : side.op,
            code = element(doc, 'td', 'split-diff__code split-diff__code--' + modifier);

        row.appendChild(element(doc, 'td', 'split-diff__num split-diff__num--' + modifier,
            side === null ? '' : String(side.number)));
        if (side === null) {
            code.appendChild(element(doc, 'span', 'split-diff__sr', filler));
        } else {
            fillCode(doc, code, side, MARKS[position]);
        }
        row.appendChild(code);
    }

    /**
     * @param {Document} doc
     * @param {{before: ?Object, after: ?Object}} line
     * @param {{fold: string, filler: string}} words
     * @returns {HTMLTableRowElement}
     */
    function lineRow(doc, line, words) {
        var changed = line.before === null || line.after === null || line.before.op !== 'equal',
            row = element(doc, 'tr', changed ? 'split-diff__row split-diff__row--change' : 'split-diff__row');

        appendSide(doc, row, line.before, 'before', words.filler);
        appendSide(doc, row, line.after, 'after', words.filler);

        return row;
    }

    /**
     * A fold's own <tbody>: the row with its button, then the rows it hides
     *
     * @param {Document} doc
     * @param {{rows: Array<Object>}} folded
     * @param {{fold: string, filler: string}} words
     * @param {function(number): string} formatNumber
     * @returns {HTMLTableSectionElement}
     */
    function foldGroup(doc, folded, words, formatNumber) {
        var group = element(doc, 'tbody', 'split-diff__fold-group'),
            control = element(doc, 'tr', 'split-diff__fold'),
            cell = element(doc, 'td', 'split-diff__fold-cell'),
            button = element(doc, 'button', 'split-diff__fold-button',
                wording.fill(words.fold, formatNumber(folded.rows.length)));

        button.setAttribute('type', 'button');
        button.setAttribute('data-action', 'fold');
        cell.setAttribute('colspan', String(COLUMNS));
        cell.appendChild(button);
        control.appendChild(cell);
        group.appendChild(control);
        folded.rows.forEach(function (line) {
            var row = lineRow(doc, line, words);

            row.hidden = true;
            group.appendChild(row);
        });

        return group;
    }

    /**
     * @param {Document} doc
     * @returns {HTMLTableColElement} The column widths: number, text, number, text
     */
    function columns(doc) {
        var group = element(doc, 'colgroup', ''),
            index;

        for (index = 0; index < COLUMNS; index += 1) {
            group.appendChild(element(doc, 'col', index % 2 === 0 ? 'split-diff__col-num' : 'split-diff__col-code'));
        }

        return group;
    }

    /**
     * @param {*} words
     * @param {*} options
     * @returns {function(number): string} How a fold's count is written
     * @throws {TypeError} When the wording lacks a text, or the number format is not a function
     */
    function checked(words, options) {
        var formatNumber = options && options.formatNumber !== undefined ? options.formatNumber : String;

        if (words === null || typeof words !== 'object'
            || typeof words.fold !== 'string' || typeof words.filler !== 'string') {
            throw new TypeError('A split diff needs the wording of its folds and of an empty side, as strings');
        }
        if (typeof formatNumber !== 'function') {
            throw new TypeError('A split diff writes its counts with a function');
        }

        return formatNumber;
    }

    /**
     * Draws a split diff
     *
     * @param {Document} doc The document to build the table with
     * @param {{rows: Array<Object>}} model What model/split-diff's split() returns
     * @param {{fold: string, filler: string}} words fold: the label of a fold, "%1" standing for the number of rows
     *     it hides; filler: the name read out for a side with nothing in it
     * @param {{formatNumber: function(number): string}=} options How the count of a fold is written; String by default
     * @returns {HTMLTableElement}
     * @throws {TypeError} When the wording or the model is not what the table needs
     */
    function render(doc, model, words, options) {
        var formatNumber = checked(words, options),
            table = element(doc, 'table', 'split-diff'),
            body = null,
            draw;

        if (model === null || typeof model !== 'object' || !Array.isArray(model.rows)) {
            throw new TypeError('A split diff draws the rows of a split model');
        }
        draw = {
            'line': function (line) {
                if (body === null) {
                    body = table.appendChild(element(doc, 'tbody', ''));
                }
                body.appendChild(lineRow(doc, line, words));
            },
            'fold': function (folded) {
                table.appendChild(foldGroup(doc, folded, words, formatNumber));
                body = null;
            }
        };
        table.appendChild(columns(doc));
        model.rows.forEach(function (row) {
            if (!Object.prototype.hasOwnProperty.call(draw, row.type)) {
                throw new TypeError('A split diff has no drawing for a row of type ' + String(row.type));
            }
            draw[row.type](row);
        });

        return table;
    }

    /**
     * Opens the fold whose button was pressed: shows the rows it hid and hides the fold's own row
     *
     * The fold's row stays in the table, hidden: a host that closes on a click outside itself checks where the click
     * landed after this ran, and a removed button would read as outside. The focus, which was on that button, moves
     * to the first row shown.
     *
     * @param {*} button The element that was clicked
     * @returns {boolean} Whether a fold was opened
     */
    function expand(button) {
        var group,
            control,
            rows;

        if (!button || typeof button.closest !== 'function' || !button.matches('[data-action="fold"]')) {
            return false;
        }
        group = button.closest('.split-diff__fold-group');
        control = button.closest('.split-diff__fold');
        if (group === null || control === null || control.parentNode !== group || control.hidden) {
            return false;
        }
        rows = Array.prototype.filter.call(group.children, function (row) {
            return row !== control;
        });
        rows.forEach(function (row) {
            row.hidden = false;
        });
        control.hidden = true;
        if (rows.length > 0) {
            rows[0].setAttribute('tabindex', '-1');
            rows[0].focus();
        }

        return true;
    }

    return Object.freeze({
        render: render,
        expand: expand
    });
});
