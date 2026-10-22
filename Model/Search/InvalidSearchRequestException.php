<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search;

use Magento\Framework\Exception\LocalizedException;

/**
 * A search asked for something malformed; its phrase is translated and written for the person searching
 */
class InvalidSearchRequestException extends LocalizedException
{
    /**
     * Why the search was refused, translated, to show the person searching
     *
     * @return string
     */
    public function getReason(): string
    {
        return $this->phrase->render();
    }
}
