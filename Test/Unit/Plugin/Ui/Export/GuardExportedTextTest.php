<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Plugin\Ui\Export;

use Hryvinskyi\ActivityLog\Model\Export\FormulaGuard;
use Hryvinskyi\ActivityLog\Model\ResourceModel\Activity\Grid\ActivityDocument;
use Hryvinskyi\ActivityLog\Plugin\Ui\Export\GuardExportedText;
use Magento\Framework\Api\AttributeValue;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\View\Element\UiComponent\DataProvider\Document;
use Magento\Ui\Model\Export\MetadataProvider;
use PHPUnit\Framework\TestCase;

/**
 * An exported activity row has its listed text columns written safe for spreadsheets; nothing else changes
 */
class GuardExportedTextTest extends TestCase
{
    private const VALUES = [
        'actor_name' => '=cmd|calc',
        'change_values' => '-1 → 2',
        'subject_label' => 'Product 24-MB01',
        'outcome' => '=not listed',
        'duration_ms' => 12,
    ];

    public function testGuardsTheListedTextColumnsTheExportAsksFor(): void
    {
        $document = $this->document(ActivityDocument::class);

        $this->plugin()->beforeGetRowData(
            $this->createMock(MetadataProvider::class),
            $document,
            ['actor_name', 'change_values', 'subject_label', 'outcome', 'duration_ms'],
            []
        );

        self::assertSame(
            ["'=cmd|calc", "'-1 → 2", 'Product 24-MB01', '=not listed', 12],
            array_map(
                static fn (string $field): mixed => $document->getCustomAttribute($field)?->getValue(),
                array_keys(self::VALUES)
            )
        );
    }

    public function testLeavesAColumnTheExportDoesNotAskForAndAnotherGridsRows(): void
    {
        $ours = $this->document(ActivityDocument::class);
        $theirs = $this->document(Document::class);
        $plugin = $this->plugin();
        $exporter = $this->createMock(MetadataProvider::class);

        $plugin->beforeGetRowData($exporter, $ours, ['subject_label'], []);
        $plugin->beforeGetRowData($exporter, $theirs, ['actor_name', 'change_values'], []);

        self::assertSame('=cmd|calc', $ours->getCustomAttribute('actor_name')?->getValue());
        self::assertSame('=cmd|calc', $theirs->getCustomAttribute('actor_name')?->getValue());
        self::assertSame('-1 → 2', $theirs->getCustomAttribute('change_values')?->getValue());
    }

    /**
     * @return GuardExportedText
     */
    private function plugin(): GuardExportedText
    {
        return new GuardExportedText(
            new FormulaGuard(),
            ['actor_name' => 'actor_name', 'change_values' => 'change_values', 'subject_label' => 'subject_label']
        );
    }

    /**
     * A row holding VALUES
     *
     * @param class-string<Document> $class
     * @return Document
     */
    private function document(string $class): Document
    {
        $values = $this->createMock(AttributeValueFactory::class);
        $values->method('create')->willReturnCallback(static fn (): AttributeValue => new AttributeValue());
        $document = new $class($values);
        foreach (self::VALUES as $field => $value) {
            $document->setCustomAttribute($field, $value);
        }

        return $document;
    }
}
