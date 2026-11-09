/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What changed inside a value: text compared word by word, token by token or line by line, and the items added to or
 * removed from a JSON list
 *
 * One longest-common-subsequence routine decides what stayed in all three text comparisons:
 * - words(): words, runs of whitespace and markup tags. Each side is compared over its first MAX_TOKENS tokens at
 *   most; the rest is returned as it is and flagged.
 * - inline(): a finer split for one line of text, where a token is a run of letters (with their combining marks),
 *   digits and underscores, a run of whitespace, or any other single character, so "2026" against "2027" or a removed
 *   "<!--" is all that changes.
 * - lines(): whole lines, compared on their text alone. Whitespace inside a line is content; the line break that ends
 *   it is not, since a browser posts a text area's line breaks as "\r\n" whatever the stored value used.
 * inline() and lines() first set aside the tokens or lines both sides start and end with, and compare only the middle:
 * at most MAX_TOKENS tokens or MAX_LINES lines a side. A longer middle is returned as all removed, then all added, and
 * flagged. inline() then moves each change at most MAX_TOKENS tokens either way to where a person would have typed it.
 * The work therefore grows with the length of the texts, plus at most the square of the cap. Pure.
 */
define([], function () {
    'use strict';

    var MAX_TOKENS = 600,
        MAX_LINES = 1000,
        TOKEN = /(\s+|<[^>]+>)/,
        INLINE_TOKEN = /[\p{L}\p{M}\p{N}_]+|\s+|[^]/gu;

    /**
     * @param {?string} text
     * @returns {string}
     */
    function textOf(text) {
        return String(text === null || text === undefined ? '' : text);
    }

    /**
     * @param {?string} text
     * @returns {Array<string>} Words, runs of whitespace and markup tags
     */
    function tokens(text) {
        return textOf(text).split(TOKEN).filter(function (token) {
            return token !== '';
        });
    }

    /**
     * @param {?string} text
     * @returns {Array<string>} Runs of letters with their combining marks, digits and underscores, runs of whitespace,
     *     and single other characters
     */
    function inlineTokens(text) {
        return textOf(text).match(INLINE_TOKEN) || [];
    }

    /**
     * @param {?string} text
     * @returns {Array<string>} The text of each line, without the "\r\n", "\n" or "\r" that ends it; no lines for an
     *     empty text, and an empty last line after a text that ends with a line break
     */
    function splitLines(text) {
        var whole = textOf(text);

        return whole === '' ? [] : whole.split(/\r\n|\n|\r/);
    }

    /**
     * @param {Array<{op: string, text: string}>} parts
     * @param {string} op
     * @param {string} text
     */
    function append(parts, op, text) {
        var last = parts[parts.length - 1];

        if (last && last.op === op) {
            last.text += text;
        } else {
            parts.push({op: op, text: text});
        }
    }

    /**
     * @param {Array<*>} before
     * @param {Array<*>} after
     * @returns {Array<Uint16Array>} table[i][j]: length of the common subsequence of before[i…] and after[j…]
     */
    function lcsTable(before, after) {
        var table = [],
            i,
            j;

        for (i = 0; i <= before.length; i += 1) {
            table.push(new Uint16Array(after.length + 1));
        }
        for (i = before.length - 1; i >= 0; i -= 1) {
            for (j = after.length - 1; j >= 0; j -= 1) {
                table[i][j] = before[i] === after[j]
                    ? table[i + 1][j + 1] + 1
                    : Math.max(table[i + 1][j], table[i][j + 1]);
            }
        }

        return table;
    }

    /**
     * Walks one longest common subsequence of two sequences from the start; on a tie a removal comes before an addition
     *
     * @param {Array<*>} before
     * @param {Array<*>} after
     * @param {function(string, number): void} emit called with the op ("equal", "delete" or "insert") and the index of
     *     the element: in before for "equal" and "delete", in after for "insert"
     */
    function align(before, after, emit) {
        var table = lcsTable(before, after),
            i = 0,
            j = 0;

        while (i < before.length && j < after.length) {
            if (before[i] === after[j]) {
                emit('equal', i);
                i += 1;
                j += 1;
            } else if (table[i + 1][j] >= table[i][j + 1]) {
                emit('delete', i);
                i += 1;
            } else {
                emit('insert', j);
                j += 1;
            }
        }
        for (; i < before.length; i += 1) {
            emit('delete', i);
        }
        for (; j < after.length; j += 1) {
            emit('insert', j);
        }
    }

    /**
     * Compares two sequences with the elements both start and end with set aside first, and the middle bounded
     *
     * The end is matched only over what the start left, so no element is counted twice when one side begins the other.
     *
     * @param {Array<*>} before
     * @param {Array<*>} after
     * @param {number} cap the most elements a side's middle may hold to be aligned
     * @param {function(string, number): void} emit as align() calls it, with indexes into the whole sequences
     * @returns {boolean} true when a middle was over the cap and was emitted as all removed, then all added
     */
    function compare(before, after, cap, emit) {
        var shorter = Math.min(before.length, after.length),
            head = 0,
            tail = 0,
            middleBefore,
            middleAfter,
            capped,
            index;

        while (head < shorter && before[head] === after[head]) {
            head += 1;
        }
        while (tail < shorter - head && before[before.length - 1 - tail] === after[after.length - 1 - tail]) {
            tail += 1;
        }
        middleBefore = before.slice(head, before.length - tail);
        middleAfter = after.slice(head, after.length - tail);
        capped = middleBefore.length > cap || middleAfter.length > cap;

        for (index = 0; index < head; index += 1) {
            emit('equal', index);
        }
        if (capped) {
            for (index = 0; index < middleBefore.length; index += 1) {
                emit('delete', head + index);
            }
            for (index = 0; index < middleAfter.length; index += 1) {
                emit('insert', head + index);
            }
        } else {
            align(middleBefore, middleAfter, function (op, at) {
                emit(op, head + at);
            });
        }
        for (index = before.length - tail; index < before.length; index += 1) {
            emit('equal', index);
        }

        return capped;
    }

    /**
     * The token at a position of three runs read as one sequence
     *
     * @param {Array<string>} previous
     * @param {Array<string>} run
     * @param {Array<string>} next
     * @param {number} at
     * @returns {string}
     */
    function tokenAt(previous, run, next, at) {
        if (at < previous.length) {
            return previous[at];
        }

        return at < previous.length + run.length ? run[at - previous.length] : next[at - previous.length - run.length];
    }

    /**
     * How far a run can move towards the end, at most MAX_TOKENS tokens: one step is possible while the token after
     * it equals its first one, since then the run read one token later holds the same tokens in the same order
     *
     * @param {Array<string>} previous
     * @param {Array<string>} run
     * @param {Array<string>} next
     * @returns {number}
     */
    function laterReach(previous, run, next) {
        var start = previous.length,
            end = start + run.length,
            steps = 0;

        while (steps < MAX_TOKENS && steps < next.length
            && tokenAt(previous, run, next, end + steps) === tokenAt(previous, run, next, start + steps)) {
            steps += 1;
        }

        return steps;
    }

    /**
     * How far a run that was first moved `from` tokens towards the end can then move back towards the start, at most
     * MAX_TOKENS tokens: one step is possible while the token before it equals its last one
     *
     * @param {Array<string>} previous
     * @param {Array<string>} run
     * @param {Array<string>} next
     * @param {number} from
     * @returns {number}
     */
    function earlierReach(previous, run, next, from) {
        var start = previous.length + from,
            end = start + run.length,
            steps = 0;

        while (steps < MAX_TOKENS && steps < start
            && tokenAt(previous, run, next, start - steps - 1) === tokenAt(previous, run, next, end - steps - 1)) {
            steps += 1;
        }

        return steps;
    }

    /**
     * Moves a run between two unchanged runs by a number of tokens, towards the end when positive: what the three
     * read as one sequence stays the same, only where the run sits in it changes
     *
     * @param {{tokens: Array<string>}} previous
     * @param {{tokens: Array<string>}} run
     * @param {{tokens: Array<string>}} next
     * @param {number} offset
     */
    function move(previous, run, next, offset) {
        var shifted;

        if (offset > 0) {
            shifted = run.tokens.concat(next.tokens.splice(0, offset));
            Array.prototype.push.apply(previous.tokens, shifted.slice(0, offset));
            run.tokens = shifted.slice(offset);
        } else if (offset < 0) {
            shifted = previous.tokens.splice(previous.tokens.length + offset, -offset).concat(run.tokens);
            run.tokens = shifted.slice(0, run.tokens.length);
            next.tokens = shifted.slice(run.tokens.length).concat(next.tokens);
        }
    }

    /**
     * Places each removal or addition that sits between two unchanged runs where a person would have typed it
     *
     * Two alignments of "<!--  <li>" and "<li>" are equally long: one keeps the first "<" and removes "!--  <", the
     * other removes "<!--  " and keeps "<li>". A run is moved as far towards the start as it can go, which gives the
     * second, readable one. The one exception is a run that can reach the end of the text: "<li>" against
     * "<!-- <li> -->" adds " -->" at the end, not "> --" before the last ">". A run moves at most MAX_TOKENS tokens
     * each way, so a change inside a long repeated stretch ("!!!…!") may stop short of its earliest place; both sides
     * still read the same, only which of the repeated tokens is marked differs. The distance is worked out from
     * positions, and each run is moved once.
     *
     * @param {Array<{op: string, tokens: Array<string>}>} runs neighbours of one op already joined; changed in place
     * @returns {Array<{op: string, tokens: Array<string>}>} the runs, with emptied ones dropped and neighbours rejoined
     */
    function slide(runs) {
        var index,
            previous,
            run,
            next,
            later,
            atEnd,
            joined = [];

        for (index = 1; index < runs.length - 1; index += 1) {
            previous = runs[index - 1];
            run = runs[index];
            next = runs[index + 1];
            if (run.op !== 'equal' && previous.op === 'equal' && next.op === 'equal') {
                later = laterReach(previous.tokens, run.tokens, next.tokens);
                atEnd = later === next.tokens.length && index + 1 === runs.length - 1;
                move(previous, run, next, atEnd
                    ? later
                    : later - earlierReach(previous.tokens, run.tokens, next.tokens, later));
            }
        }
        runs.forEach(function (each) {
            var last = joined[joined.length - 1];

            if (each.tokens.length === 0) {
                return;
            }
            if (last && last.op === each.op) {
                last.tokens = last.tokens.concat(each.tokens);
            } else {
                joined.push(each);
            }
        });

        return joined;
    }

    /**
     * @param {Array<{op: string, text: string}>} parts
     * @returns {number} The parts that are not "equal"
     */
    function editsIn(parts) {
        return parts.filter(function (part) {
            return part.op !== 'equal';
        }).length;
    }

    /**
     * The word-level diff of two texts
     *
     * @param {?string} before
     * @param {?string} after
     * @returns {{parts: Array<{op: string, text: string}>, edits: number, capped: boolean,
     *     rest: {before: string, after: string}}} op "equal", "delete" or "insert", neighbours of one op joined;
     *     edits counts the parts that are not "equal"; rest holds what lies beyond the compared tokens
     */
    function words(before, after) {
        var allBefore = tokens(before),
            allAfter = tokens(after),
            left = allBefore.slice(0, MAX_TOKENS),
            right = allAfter.slice(0, MAX_TOKENS),
            parts = [];

        align(left, right, function (op, index) {
            append(parts, op, op === 'insert' ? right[index] : left[index]);
        });

        return {
            parts: parts,
            edits: editsIn(parts),
            capped: allBefore.length > MAX_TOKENS || allAfter.length > MAX_TOKENS,
            rest: {before: allBefore.slice(MAX_TOKENS).join(''), after: allAfter.slice(MAX_TOKENS).join('')}
        };
    }

    /**
     * The fine diff of two texts, meant for one line against another
     *
     * @param {?string} before
     * @param {?string} after
     * @returns {{parts: Array<{op: string, text: string}>, edits: number, capped: boolean}} op "equal", "delete" or
     *     "insert", neighbours of one op joined, each removal or addition between unchanged text as early as it can
     *     be; edits counts the parts that are not "equal"; capped when the middle was too long to align
     */
    function inline(before, after) {
        var left = inlineTokens(before),
            right = inlineTokens(after),
            runs = [],
            capped,
            parts;

        capped = compare(left, right, MAX_TOKENS, function (op, index) {
            var token = op === 'insert' ? right[index] : left[index],
                last = runs[runs.length - 1];

            if (last && last.op === op) {
                last.tokens.push(token);
            } else {
                runs.push({op: op, tokens: [token]});
            }
        });
        parts = slide(runs).map(function (run) {
            return {op: run.op, text: run.tokens.join('')};
        });

        return {parts: parts, edits: editsIn(parts), capped: capped};
    }

    /**
     * The line-level diff of two texts
     *
     * A line ends after "\r\n", "\n" or "\r"; two lines are equal when their text is, whichever line break ends
     * them. An empty text has no lines; a text that ends with a line break has an empty last line.
     *
     * @param {?string} before
     * @param {?string} after
     * @returns {{lines: Array<{op: string, text: string}>, capped: boolean}} one entry a line, in order, op "equal",
     *     "delete" or "insert", text without its line break; between two unchanged lines the removed lines come
     *     first, then the added ones, each in its own order; capped when the middle was too long to align
     */
    function lines(before, after) {
        var left = splitLines(before),
            right = splitLines(after),
            ids = new Map(),
            result = [],
            deleted = [],
            inserted = [],
            take,
            capped;

        /**
         * @param {string} line
         * @returns {number} The same number for every line with the same text
         */
        function idOf(line) {
            if (!ids.has(line)) {
                ids.set(line, ids.size);
            }

            return ids.get(line);
        }

        /**
         * Writes out the changed lines gathered since the last unchanged one: the removed, then the added
         */
        function flush() {
            deleted.concat(inserted).forEach(function (line) {
                result.push(line);
            });
            deleted = [];
            inserted = [];
        }

        take = {
            'equal': function (index) {
                flush();
                result.push({op: 'equal', text: left[index]});
            },
            'delete': function (index) {
                deleted.push({op: 'delete', text: left[index]});
            },
            'insert': function (index) {
                inserted.push({op: 'insert', text: right[index]});
            }
        };
        capped = compare(left.map(idOf), right.map(idOf), MAX_LINES, function (op, index) {
            take[op](index);
        });
        flush();

        return {lines: result, capped: capped};
    }

    /**
     * @param {?string} text
     * @returns {?Array<*>} The list the text holds, an empty one for no value, or null when it holds no list
     */
    function listOf(text) {
        var value;

        if (text === null || text === undefined || text === '') {
            return [];
        }
        try {
            value = JSON.parse(text);
        } catch (notJson) {
            return null;
        }

        return Array.isArray(value) ? value : null;
    }

    /**
     * @param {*} item
     * @returns {string} A string item as it is, anything else as JSON
     */
    function shown(item) {
        return typeof item === 'string' ? item : JSON.stringify(item);
    }

    /**
     * The items one JSON list gained and lost against another; an item repeated n times counts n times
     *
     * @param {?string} before
     * @param {?string} after
     * @returns {{ok: boolean, added: Array<string>, removed: Array<string>, same: Array<string>}}
     *     ok false (and empty lists) when either side is not a JSON list
     */
    function array(before, after) {
        var left = listOf(before),
            right = listOf(after),
            unmatched = Object.create(null),
            removed = [],
            added = [],
            same = [];

        if (left === null || right === null) {
            return {ok: false, added: [], removed: [], same: []};
        }

        right.forEach(function (item) {
            var key = JSON.stringify(item);

            unmatched[key] = (unmatched[key] || 0) + 1;
        });
        left.forEach(function (item) {
            var key = JSON.stringify(item);

            if (unmatched[key] > 0) {
                unmatched[key] -= 1;
                same.push(shown(item));
            } else {
                removed.push(shown(item));
            }
        });
        right.forEach(function (item) {
            var key = JSON.stringify(item);

            if (unmatched[key] > 0) {
                unmatched[key] -= 1;
                added.push(shown(item));
            }
        });

        return {ok: true, added: added, removed: removed, same: same};
    }

    return Object.freeze({
        MAX_TOKENS: MAX_TOKENS,
        MAX_LINES: MAX_LINES,
        words: words,
        inline: inline,
        lines: lines,
        array: array
    });
});
