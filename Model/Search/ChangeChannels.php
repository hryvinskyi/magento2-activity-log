<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search;

use Hryvinskyi\ActivityLog\Model\Data\Activity;

/**
 * The channels whose activities record changes, which "Changes only" keeps
 *
 * A module whose channel records changes adds its code to the "changeChannels" argument of this type. With no such
 * channel the switch has nothing to keep, so a search ignores it and the search page does not offer it.
 *
 * @api
 */
class ChangeChannels
{
    /**
     * @var list<string>
     */
    private array $codes = [];

    /**
     * @param array<string,string> $changeChannels
     * @throws \InvalidArgumentException When an item is not a channel code
     */
    public function __construct(array $changeChannels = [])
    {
        foreach ($changeChannels as $code) {
            if (preg_match(Activity::CHANNEL_PATTERN, $code) !== 1) {
                throw new \InvalidArgumentException(sprintf('"%s" is not a channel code.', $code));
            }
            $this->codes[] = $code;
        }
        $this->codes = array_values(array_unique($this->codes));
    }

    /**
     * Codes of the channels that record changes, possibly none
     *
     * @return list<string>
     */
    public function codes(): array
    {
        return $this->codes;
    }
}
