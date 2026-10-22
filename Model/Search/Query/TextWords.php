<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Query;

/**
 * The words a plain-text filter is searched for, one at a time
 *
 * Words are split on whitespace and lose the double quotes around them; a word typed twice, in any case, counts once,
 * and only the first MAX_WORDS distinct words are kept, so one long filter cannot grow the query without bound. Each
 * word is searched on its own, which lets two words be found in different places of one activity.
 */
class TextWords
{
    /**
     * Most words of one plain-text filter that are searched
     */
    public const MAX_WORDS = 8;

    /**
     * The distinct words of a typed value, in the order typed, at most MAX_WORDS
     *
     * @param string $value
     * @return list<string>
     */
    public function of(string $value): array
    {
        $words = [];
        foreach (preg_split('/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $word = trim($word, '"');
            $folded = mb_strtolower($word);
            if ($word === '' || isset($words[$folded])) {
                continue;
            }

            $words[$folded] = $word;
            if (count($words) === self::MAX_WORDS) {
                break;
            }
        }

        return array_values($words);
    }
}
