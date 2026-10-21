<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Plugin\Ui\Export;

use Hryvinskyi\ActivityLog\Model\Presentation\TimeFormatter;
use Magento\Framework\Api\Search\DocumentInterface;
use Magento\Ui\Model\Export\MetadataProvider;

/**
 * The activity grid stores time as milliseconds; an exported file shows it as a date in the store timezone
 */
class FormatActivityTime
{
    public const FIELD = 'occurred_at_ms';

    /**
     * @param TimeFormatter $timeFormatter
     */
    public function __construct(
        private readonly TimeFormatter $timeFormatter
    ) {
    }

    /**
     * Rewrites the time field of a row before the export reads it; the exporter itself is not needed
     *
     * @param MetadataProvider $exporter
     * @param DocumentInterface $document
     * @param array<int,string> $fields
     * @param array<string,mixed> $options
     * @return array{DocumentInterface, array<int, string>, array<string, mixed>}
     */
    public function beforeGetRowData(
        MetadataProvider $exporter,
        DocumentInterface $document,
        $fields,
        $options
    ): array {
        $value = in_array(self::FIELD, $fields, true) ? $document->getCustomAttribute(self::FIELD)?->getValue() : null;
        if (is_numeric($value)) {
            $document->setCustomAttribute(self::FIELD, $this->timeFormatter->dateTime((int) $value));
        }

        return [$document, $fields, $options];
    }
}
