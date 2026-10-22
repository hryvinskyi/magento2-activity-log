<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Controller;

use Hryvinskyi\ActivityLog\Api\Data\Search\RangePreset;
use Hryvinskyi\ActivityLog\Model\Search\ChangeChannels;
use Hryvinskyi\ActivityLog\Model\Search\ExamplePool;
use Hryvinskyi\ActivityLog\Model\Search\KeyDescriptionPool;
use Hryvinskyi\ActivityLog\Model\Search\Preference\ResultView;
use Hryvinskyi\ActivityLog\Model\Search\Preference\SearchPreferenceStorageInterface;
use Hryvinskyi\ActivityLog\Model\Search\Query\SearchQueryBuilder;
use Hryvinskyi\ActivityLog\Test\Integration\Controller\Fixture\FixedExample;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\FixedUserContext;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\SearchFixture;
use Hryvinskyi\ActivityLog\ViewModel\SearchPage;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The activity search page rendered through the real admin stack and layout; the parent class adds the ACL allow
 * and deny tests
 */
#[DbIsolation(true)]
class SearchPageTest extends AbstractBackendController
{
    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_ActivityLog::view';

    /**
     * @var string
     */
    protected $uri = 'backend/activitylog/activity/index';

    protected function setUp(): void
    {
        parent::setUp();
        // The page's view model is shared and reads its collaborators once; each test renders a fresh one
        $this->_objectManager->removeSharedInstance(SearchPage::class);
    }

    protected function tearDown(): void
    {
        $this->_objectManager->removeSharedInstance(ExamplePool::class);
        $this->_objectManager->removeSharedInstance(ChangeChannels::class);
        $this->_objectManager->removeSharedInstance(SearchPage::class);
        FixedUserContext::signOut($this->_objectManager);
        parent::tearDown();
    }

    public function testPrintsTheEndpointsWithTheSecretKeyAndWhatTheScriptNeeds(): void
    {
        $urlBuilder = SearchFixture::service(UrlInterface::class);
        $urlBuilder->turnOnSecretKey();
        $pageUrl = $urlBuilder->getUrl('hryvinskyi_activitylog/activity/index');
        $this->dispatch(substr($pageUrl, (int) strpos($pageUrl, 'backend/')));
        $config = $this->printedConfig('search-config');

        $endpoints = ['searchUrl' => 'search', 'suggestUrl' => 'suggest', 'preferenceUrl' => 'savePreference'];
        foreach ($endpoints as $name => $action) {
            self::assertIsString($config[$name] ?? null);
            self::assertStringContainsString('/activitylog/activity/' . $action . '/key/', $config[$name]);
            self::assertSame(
                $urlBuilder->getUrl('hryvinskyi_activitylog/activity/' . $action),
                $config[$name],
                $name . ' carries the secret key the admin router accepts'
            );
        }
        self::assertSame(SearchFixture::service(FormKey::class)->getFormKey(), $config['formKey'] ?? null);
        self::assertSame(
            SearchFixture::service(TimezoneInterface::class)->getConfigTimezone(),
            $config['timeZone'] ?? null
        );
        self::assertIsInt($config['serverNowMs'] ?? null);
        self::assertEqualsWithDelta(microtime(true) * 1000, $config['serverNowMs'], 120_000);

        $keys = $config['keys'] ?? null;
        self::assertIsArray($keys);
        $codes = array_column($keys, 'key');
        foreach (['by', 'via', 'channel', 'outcome', 'store', 'request', 'text'] as $journalKey) {
            self::assertContains($journalKey, $codes);
        }
        self::assertSame(
            ['view' => ResultView::Timeline->value, 'changesOnly' => true],
            $config['preferences'] ?? null,
            'an admin who never chose sees the timeline of changes only'
        );
        self::assertSame('activity_search', is_array($config['grid'] ?? null) ? $config['grid']['param'] : null);

        $timeline = $this->printedConfig('activity-timeline-config');
        self::assertIsArray($timeline['verbs'] ?? null);
        self::assertSame('tried to change', $timeline['verbs']['tried_to_change'] ?? null);
        self::assertIsArray($timeline['wording'] ?? null);
        self::assertSame('(empty)', $timeline['wording']['empty'] ?? null);
        self::assertSame('Admin', is_array($timeline['actorTypes'] ?? null) ? $timeline['actorTypes']['admin'] : null);
    }

    public function testPrintsWhatTheTimelineRendersWith(): void
    {
        $this->dispatch($this->uri);
        $timeline = $this->printedConfig('activity-timeline-config');
        $body = $this->body();

        self::assertSame(SearchQueryBuilder::CANDIDATE_LIMIT, $timeline['candidateLimit'] ?? null);
        $wording = is_array($timeline['wording'] ?? null) ? $timeline['wording'] : [];
        self::assertSame('Today · %1', $wording['dayToday'] ?? null);
        self::assertSame('Yesterday · %1', $wording['dayYesterday'] ?? null);
        self::assertSame('Load %1 more', $wording['loadMore'] ?? null);
        self::assertSame(
            ['success', 'failure', 'denied'],
            array_keys(is_array($timeline['outcomes'] ?? null) ? $timeline['outcomes'] : [])
        );
        $templates = ['results', 'day', 'item', 'row', 'masked', 'sibling', 'skeleton', 'state', 'empty-log',
            'example'];
        foreach ($templates as $template) {
            self::assertSame(
                1,
                substr_count($body, sprintf('<template data-template="%s">', $template)),
                sprintf('the timeline clones the "%s" template', $template)
            );
        }
        self::assertSame(1, substr_count($body, 'data-role="activity-timeline-assets"'), 'printed once per page');
    }

    public function testPrintsTheContributedExamples(): void
    {
        $example = new FixedExample('Test example', [['key' => 'by', 'value' => 'jane']], RangePreset::LastThirtyDays);
        $this->_objectManager->addSharedInstance(new ExamplePool(['test' => $example]), ExamplePool::class);
        $this->dispatch($this->uri);

        self::assertSame(
            [['title' => 'Test example', 'chips' => [['key' => 'by', 'value' => 'jane']], 'preset' => '30d']],
            $this->printedConfig('search-config')['examples'] ?? null
        );
        self::assertMatchesRegularExpression('#data-example="0">\s*Test example\s*</button>#', $this->body());
    }

    public function testPrintsNoExampleOfItsOwnWhenNoneIsContributed(): void
    {
        $this->_objectManager->addSharedInstance(new ExamplePool([]), ExamplePool::class);
        $this->dispatch($this->uri);

        self::assertSame([], $this->printedConfig('search-config')['examples'] ?? null);
        self::assertStringNotContainsString('data-role="examples"', $this->body(), 'no "Try:" row without examples');
    }

    public function testDoesNotOfferChangesOnlyWhileNoChannelRecordsChanges(): void
    {
        $this->_objectManager->addSharedInstance(new ChangeChannels([]), ChangeChannels::class);
        $this->dispatch($this->uri);

        $config = $this->printedConfig('search-config');
        self::assertSame([], $config['changeChannels'] ?? null);
        $preferences = $config['preferences'] ?? null;
        self::assertSame(false, is_array($preferences) ? $preferences['changesOnly'] : null);
        self::assertStringNotContainsString('data-role="changes-only"', $this->body());
    }

    public function testOffersChangesOnlyOnceAChannelRecordsChanges(): void
    {
        $this->_objectManager->addSharedInstance(new ChangeChannels(['test' => 'admin']), ChangeChannels::class);
        $this->dispatch($this->uri);

        self::assertSame(['admin'], $this->printedConfig('search-config')['changeChannels'] ?? null);
        self::assertStringContainsString('data-role="changes-only" checked', $this->body());
    }

    public function testOpensOnTheViewTheAdminChoseLast(): void
    {
        $user = $this->_auth->getUser();
        self::assertInstanceOf(\Magento\User\Model\User::class, $user);
        $userId = $user->getId();
        self::assertIsNumeric($userId);
        FixedUserContext::signIn($this->_objectManager, (int) $userId);
        SearchFixture::service(SearchPreferenceStorageInterface::class)->save(ResultView::Grid, false);
        $this->dispatch($this->uri);

        $config = $this->printedConfig('search-config');
        self::assertSame(['view' => 'grid', 'changesOnly' => false], $config['preferences'] ?? null);
        $body = $this->body();
        self::assertMatchesRegularExpression('#data-role="timeline-view"\s+hidden>#', $body);
        self::assertMatchesRegularExpression('#data-role="grid-view">\s*<div[^>]*activity-log-grid#', $body);
    }

    public function testTheFiltersPanelHasAControlPerKeyItsDescriptionPutsThere(): void
    {
        $this->dispatch($this->uri);
        $body = $this->body();

        $shown = 0;
        foreach (SearchFixture::service(KeyDescriptionPool::class)->all() as $description) {
            $control = sprintf('data-filter-key="%s"', $description->getKey());
            if (!$description->isInPanel()) {
                self::assertStringNotContainsString($control, $body, $description->getKey());
                continue;
            }
            $shown++;
            self::assertSame(1, substr_count($body, $control), $description->getKey());
            if ($description->getCompanionLabel() === '') {
                continue;
            }
            self::assertStringContainsString(sprintf('data-companion-of="%s"', $description->getKey()), $body);
            foreach ($description->getOptionTargets() as $option => $target) {
                self::assertMatchesRegularExpression(
                    sprintf('#<option value="%s"\s+data-target="%s"\s*>#', preg_quote($option, '#'), $target),
                    $body
                );
            }
        }
        self::assertGreaterThan(0, $shown);
        self::assertStringNotContainsString('data-filter-key="text"', $body, 'plain words belong to the bar');
        self::assertStringContainsString('<option value="denied">', $body);
    }

    /**
     * The JSON a script element of the rendered page holds
     *
     * @param string $role
     * @return array<mixed>
     */
    private function printedConfig(string $role): array
    {
        $found = preg_match(
            sprintf('#<script type="application/json" data-role="%s">(.*?)</script>#s', preg_quote($role, '#')),
            $this->body(),
            $match
        );
        self::assertSame(1, $found, sprintf('The page prints its "%s" config', $role));
        $config = json_decode($match[1], true);
        self::assertIsArray($config);

        return $config;
    }

    /**
     * @return string
     */
    private function body(): string
    {
        $response = $this->getResponse();
        self::assertInstanceOf(\Magento\TestFramework\Response::class, $response);

        return (string) $response->getBody();
    }
}
