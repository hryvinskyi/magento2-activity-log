<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Data\Search;

/**
 * Which links a search result may carry to the browser, which puts them into href attributes
 *
 * Only an http(s) URL or a path from the site root, without whitespace or control characters: a "javascript:" or
 * "data:" link would run in the admin's session, and a protocol-relative "//host" one would leave the site.
 */
class LinkPolicy
{
    private const SAFE = '~^(?:https?://[^\s/]+(?:/\S*)?|/(?!/)\S*)$~i';

    /**
     * Refuse a link the browser must not follow
     *
     * @param string $url
     * @return void
     * @throws \InvalidArgumentException When the link is of another kind
     */
    public function assertSafe(string $url): void
    {
        if (preg_match(self::SAFE, $url) !== 1 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            throw new \InvalidArgumentException(
                sprintf('"%s" is neither an http(s) URL nor a path from the site root.', $url)
            );
        }
    }
}
