<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Ui;

use Hryvinskyi\ActivityLog\Model\Search\Detail\DetailProviderPool;
use Hryvinskyi\ActivityLog\Model\Search\Detail\EventDetails;
use Hryvinskyi\ActivityLog\Model\Search\Grid\PageColumns;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\FixedDetailProvider;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\SearchFixture;
use Hryvinskyi\ActivityLog\Ui\DataProvider\ActivitySearchParams;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\ObjectManager\ConfigInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\Request;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Ui\Model\Export\ConvertToCsv;
use PHPUnit\Framework\TestCase;

/**
 * The grid's CSV export through Magento's real converter, as the export button asks for it: every match of the
 * search, past the bound a rendered grid stops counting at, with the change columns filled from the detail providers
 *
 * A test detail provider is added to the "detailProviders" argument the way a module's di.xml adds one; the argument
 * is restored after each test.
 */
#[AppArea('adminhtml')]
#[DbIsolation(true)]
class ActivityGridExportTest extends TestCase
{
    private const LISTING = 'hryvinskyi_activity_log_listing';
    private const NOW_MS = 1_792_500_000_000;
    private const MATCHES = 10_250;
    private const DETAILS = 'zz_test_grid_export_details';

    /**
     * Services holding the provider pool, rebuilt after its argument changes
     */
    private const HOLDERS = [DetailProviderPool::class, EventDetails::class, PageColumns::class];

    /**
     * @var array<mixed>
     */
    private array $originalProviders = [];

    protected function setUp(): void
    {
        $arguments = SearchFixture::service(ConfigInterface::class)->getArguments(DetailProviderPool::class);
        $this->originalProviders = is_array($arguments) && is_array($arguments['detailProviders'] ?? null)
            ? $arguments['detailProviders']
            : [];
        self::objectManager()->configure([
            self::DETAILS => ['type' => FixedDetailProvider::class, 'arguments' => ['channel' => 'zz_detail']],
            DetailProviderPool::class => ['arguments' => ['detailProviders' => $this->originalProviders + [
                self::DETAILS => ['instance' => self::DETAILS],
            ]]],
        ]);
        $this->forgetHolders();
    }

    protected function tearDown(): void
    {
        self::objectManager()->configure([
            DetailProviderPool::class => ['arguments' => ['detailProviders' => $this->originalProviders]],
        ]);
        $this->forgetHolders();
        self::objectManager()->removeSharedInstance(self::DETAILS);
        foreach ([ActivitySearchParams::PARAM, 'namespace', 'excluded'] as $param) {
            self::request()->setParam($param, null);
        }
    }

    public function testTheExportHoldsEveryMatchOfTheSearchWithWhatEachChanged(): void
    {
        $fixture = new SearchFixture();
        $fixture->clear();
        $rows = [];
        for ($i = 0; $i < self::MATCHES; $i++) {
            $rows[] = [self::NOW_MS - $i * 10, ['channel' => 'zz_detail', 'correlation_id' => 'op-export-' . $i]];
        }
        $rows[] = [self::NOW_MS - 5, ['channel' => 'zz_not_searched']];
        $fixture->insertMany($rows);

        $request = self::request();
        $request->setParam('namespace', self::LISTING);
        $request->setParam('excluded', 'false');
        $request->setParam(ActivitySearchParams::PARAM, [
            'from' => (string) (self::NOW_MS - self::MATCHES * 10),
            'to' => (string) self::NOW_MS,
            'c' => ['channel:zz_detail'],
            'changes' => '0',
        ]);

        $lines = $this->export();

        $header = array_shift($lines);
        self::assertIsArray($header);
        self::assertCount(self::MATCHES, $lines, 'Every match is exported, not only the counted ten thousand');
        $columns = array_flip(array_map('strval', $header));
        foreach (['Time', 'Channel', 'Field', 'Before → After', 'Scope', 'Correlation ID'] as $label) {
            self::assertArrayHasKey($label, $columns);
        }
        self::assertArrayNotHasKey('Date', $columns, 'The day filter\'s column is gone');

        $correlations = array_column($lines, $columns['Correlation ID']);
        $first = $lines[(int) array_search('op-export-0', $correlations, true)];
        self::assertSame('zz_detail', $first[$columns['Channel']]);
        self::assertSame('Price, Description, api_secret and 1 more', $first[$columns['Field']]);
        $values = explode("\n", $first[$columns['Before → After']]);
        self::assertSame('Price: 34.00 → 39.00', $values[0]);
        self::assertStringStartsWith('Description: aaaa', $values[1]);
        self::assertSame('api_secret: •••••• → ••••••', $values[2]);
        self::assertSame('Default Store View', $first[$columns['Scope']]);

        self::assertCount(self::MATCHES, array_unique($correlations), 'No activity is exported twice');
        self::assertNotContains('op-search-test', $correlations, 'An activity outside the search is not exported');
    }

    public function testRecordedTextIsExportedSoASpreadsheetNeverRunsItAsAFormula(): void
    {
        $fixture = new SearchFixture();
        $fixture->clear();
        $fixture->insert(self::NOW_MS, [
            'channel' => 'zz_detail',
            'actor_name' => '=HYPERLINK("http://example.test","x")',
            'action' => '+SUM(1,1)',
            'subject_label' => '@cmd',
            'correlation_id' => '-op-export-formula',
            'user_agent' => 'Plain Agent',
        ]);

        $request = self::request();
        $request->setParam('namespace', self::LISTING);
        $request->setParam('excluded', 'false');
        $request->setParam(ActivitySearchParams::PARAM, [
            'from' => (string) (self::NOW_MS - 1000),
            'to' => (string) self::NOW_MS,
            'c' => [],
            'changes' => '0',
        ]);

        $lines = $this->export();

        $header = array_shift($lines);
        self::assertIsArray($header);
        self::assertCount(1, $lines);
        $row = array_combine(array_map('strval', $header), $lines[0]);
        self::assertSame(
            ['\'=HYPERLINK("http://example.test","x")', '\'+SUM(1,1)', '\'-op-export-formula'],
            [$row['Actor'] ?? null, $row['Action'] ?? null, $row['Correlation ID'] ?? null]
        );
        self::assertNotSame('', $row['Time'] ?? '', 'The time is still written as a date');
        self::assertStringNotContainsString('\'', $row['Time'] ?? '');
    }

    /**
     * The exported file's rows, the header first; the file is removed
     *
     * @return list<list<string>>
     */
    private function export(): array
    {
        $converter = Bootstrap::getObjectManager()->create(ConvertToCsv::class, [
            'filter' => Bootstrap::getObjectManager()->create(Filter::class),
        ]);
        self::assertInstanceOf(ConvertToCsv::class, $converter);
        $file = $converter->getCsvFile();
        self::assertIsString($file['value'] ?? null);

        $directory = SearchFixture::service(Filesystem::class)->getDirectoryWrite(DirectoryList::VAR_DIR);
        $content = $directory->readFile($file['value']);
        $directory->delete($file['value']);

        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $content);
        rewind($stream);
        $lines = [];
        while (($line = fgetcsv($stream, null, ',', '"', '\\')) !== false) {
            $lines[] = array_map(static fn (mixed $cell): string => (string) $cell, $line);
        }
        fclose($stream);

        return $lines;
    }

    /**
     * Drop the shared instances that hold the provider pool
     *
     * @return void
     */
    private function forgetHolders(): void
    {
        foreach (self::HOLDERS as $holder) {
            self::objectManager()->removeSharedInstance($holder);
        }
    }

    /**
     * @return Request
     */
    private static function request(): Request
    {
        $request = Bootstrap::getObjectManager()->get(RequestInterface::class);
        self::assertInstanceOf(Request::class, $request);

        return $request;
    }

    /**
     * @return ObjectManager
     */
    private static function objectManager(): ObjectManager
    {
        $objectManager = Bootstrap::getObjectManager();
        self::assertInstanceOf(ObjectManager::class, $objectManager);

        return $objectManager;
    }
}
