<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Controller;

use Hryvinskyi\ActivityLog\Model\Cache\Type\SearchCache;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\SearchFixture;
use Magento\Framework\App\Cache\StateInterface;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The suggestions endpoint through the real admin stack; the parent class adds the ACL allow and deny tests
 */
#[DbIsolation(true)]
class SuggestEndpointTest extends AbstractBackendController
{
    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_ActivityLog::view';

    /**
     * @var string
     */
    protected $uri = 'backend/activitylog/activity/suggest';

    /**
     * @var string
     */
    protected $httpMethod = 'GET';

    /**
     * @var bool
     */
    private bool $cacheWasEnabled = false;

    protected function setUp(): void
    {
        parent::setUp();
        $state = SearchFixture::service(StateInterface::class);
        $this->cacheWasEnabled = $state->isEnabled(SearchCache::TYPE_IDENTIFIER);
        // Lists are read fresh, so they hold this test's rows whatever an earlier request kept
        $state->setEnabled(SearchCache::TYPE_IDENTIFIER, false);
    }

    protected function tearDown(): void
    {
        SearchFixture::service(StateInterface::class)
            ->setEnabled(SearchCache::TYPE_IDENTIFIER, $this->cacheWasEnabled);
        parent::tearDown();
    }

    public function testAnswersWithEveryKeysHintAndTheValuesRecentActivitiesHold(): void
    {
        $fixture = new SearchFixture();
        $fixture->clear();
        $nowMs = (int) floor(microtime(true) * 1000);
        $fixture->insert($nowMs - 60_000, ['actor_name' => 'Suggested Actor', 'actor_type' => 'integration']);
        $fixture->insert($nowMs - 30_000, ['actor_name' => 'Suggested Actor', 'actor_type' => 'integration']);

        $request = $this->getRequest();
        self::assertInstanceOf(\Magento\TestFramework\Request::class, $request);
        $request->setMethod('GET');
        $this->dispatch($this->uri);

        $response = $this->getResponse();
        self::assertInstanceOf(\Magento\TestFramework\Response::class, $response);
        self::assertSame(200, $response->getHttpResponseCode());
        $cacheControl = $response->getHeader('Cache-Control');
        self::assertInstanceOf(\Laminas\Http\Header\HeaderInterface::class, $cacheControl);
        self::assertStringContainsString('no-store', $cacheControl->getFieldValue());

        $body = json_decode((string) $response->getBody(), true);
        self::assertIsArray($body);
        self::assertSame(['keys', 'lists'], array_keys($body));

        $keys = is_array($body['keys']) ? $body['keys'] : [];
        $byKey = array_column($keys, null, 'key');
        $groups = [
            'by' => 'people',
            'via' => 'people',
            'channel' => 'other',
            'outcome' => 'other',
            'store' => 'other',
            'request' => 'other',
            'text' => 'other',
        ];
        foreach ($groups as $key => $group) {
            self::assertIsArray($byKey[$key] ?? null, $key);
            self::assertSame(['key', 'label', 'hint', 'group', 'matchesFieldNames'], array_keys($byKey[$key]));
            self::assertNotSame('', $byKey[$key]['label']);
            self::assertNotSame('', $byKey[$key]['hint']);
            self::assertSame($group, $byKey[$key]['group'], $key);
            self::assertFalse($byKey[$key]['matchesFieldNames'], $key);
        }
        foreach ($keys as $description) {
            self::assertIsArray($description);
            self::assertContains($description['group'] ?? null, ['fields', 'records', 'people', 'types', 'other']);
            self::assertIsBool($description['matchesFieldNames'] ?? null);
        }

        $lists = is_array($body['lists']) ? $body['lists'] : [];
        self::assertSame(
            [['value' => '"Suggested Actor"', 'label' => 'Suggested Actor', 'hint' => 'Integration', 'count' => 2]],
            $lists['by'] ?? null,
            'A person is offered by their exact name'
        );
        self::assertSame(
            ['value' => 'success', 'label' => 'Success', 'hint' => '', 'count' => null],
            is_array($lists['outcome'] ?? null) ? $lists['outcome'][0] : null
        );
        self::assertNotEmpty($lists['channel'] ?? []);
        self::assertArrayNotHasKey('request', $lists, 'No list for a key nobody suggests');
    }
}
