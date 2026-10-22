<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\ResourceModel\Activity\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\Document;

/**
 * One row of the activity grid, as the grid's collection hands it out
 *
 * It is a document like any grid's; its own type lets what the export does with the grid's rows (dates, text written
 * safe for spreadsheets) apply to this grid's rows and to no other grid's.
 */
class ActivityDocument extends Document
{
}
