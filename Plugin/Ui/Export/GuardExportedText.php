<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Plugin\Ui\Export;

use Hryvinskyi\ActivityLog\Model\Export\FormulaGuard;
use Hryvinskyi\ActivityLog\Model\ResourceModel\Activity\Grid\ActivityDocument;
use Magento\Framework\Api\Search\DocumentInterface;
use Magento\Ui\Model\Export\MetadataProvider;

/**
 * An activity grid row's recorded text, written into an exported file so that a spreadsheet never runs it as a
 * formula (see FormulaGuard)
 *
 * Applies to the activity grid's rows only, and to the text columns listed in "fields": the ones whose values come
 * from people and clients. Other grids' exports are left as they are.
 */
class GuardExportedText
{
    /**
     * @var list<string>
     */
    private readonly array $fields;

    /**
     * @param FormulaGuard $formulaGuard
     * @param array<string> $fields Codes of the text columns to guard
     */
    public function __construct(
        private readonly FormulaGuard $formulaGuard,
        array $fields = []
    ) {
        $this->fields = array_values($fields);
    }

    /**
     * Rewrites the row's text before the export reads it; the exporter itself is not needed
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
        if (!$document instanceof ActivityDocument) {
            return [$document, $fields, $options];
        }

        foreach (array_intersect($this->fields, $fields) as $field) {
            $value = $document->getCustomAttribute($field)?->getValue();
            if (is_string($value)) {
                $document->setCustomAttribute($field, $this->formulaGuard->cell($value));
            }
        }

        return [$document, $fields, $options];
    }
}
