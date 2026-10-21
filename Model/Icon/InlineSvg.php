<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Icon;

/**
 * Turns an SVG icon file into markup to print inside a page: no comments, no layout whitespace, hidden from
 * assistive technology, with the page's classes
 */
class InlineSvg
{
    private const COMMENT = '/<!--.*?-->/s';
    private const SPACE_BETWEEN_TAGS = '/>\s+</';
    private const SPACE = '/\s+/';
    private const SVG_TAG = '/<svg\b/';
    private const SVG_CLASS = '/(<svg\b[^>]*?\bclass=")/';
    private const CLASSES = '/^[A-Za-z0-9_ -]*$/';

    /**
     * The file's SVG ready to print, with the classes put first in its class attribute
     *
     * @param string $file The icon file's text
     * @param string $class CSS classes: letters, digits, "-", "_" and spaces
     * @return string
     * @throws \InvalidArgumentException When the classes hold anything else
     */
    public function markup(string $file, string $class): string
    {
        if (preg_match(self::CLASSES, $class) !== 1) {
            throw new \InvalidArgumentException('An icon class holds only letters, digits, "-", "_" and spaces.');
        }

        $svg = (string) preg_replace(self::COMMENT, '', $file);
        $svg = (string) preg_replace(self::SPACE_BETWEEN_TAGS, '><', trim($svg));
        $svg = (string) preg_replace(self::SPACE, ' ', $svg);
        $svg = (string) preg_replace(self::SVG_TAG, '<svg aria-hidden="true" focusable="false"', $svg, 1);

        $class = trim($class);

        return $class === '' ? $svg : (string) preg_replace(self::SVG_CLASS, '${1}' . $class . ' ', $svg, 1);
    }
}
