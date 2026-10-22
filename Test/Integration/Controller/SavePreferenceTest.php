<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Controller;

use Hryvinskyi\ActivityLog\Model\Search\Preference\ResultView;
use Hryvinskyi\ActivityLog\Model\Search\Preference\SearchPreferenceStorageInterface;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\FixedUserContext;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\SearchFixture;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The search page's preference endpoint through the real admin stack: a POST with the form key, admins with
 * "view" only; the parent class adds the ACL allow and deny tests
 */
#[DbIsolation(true)]
class SavePreferenceTest extends AbstractBackendController
{
    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_ActivityLog::view';

    /**
     * @var string
     */
    protected $uri = 'backend/activitylog/activity/savePreference';

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_POST;

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->_auth->getUser();
        self::assertInstanceOf(\Magento\User\Model\User::class, $user);
        $userId = $user->getId();
        self::assertIsNumeric($userId);
        FixedUserContext::signIn($this->_objectManager, (int) $userId);
    }

    protected function tearDown(): void
    {
        FixedUserContext::signOut($this->_objectManager);
        parent::tearDown();
    }

    public function testRemembersTheViewAndChangesOnlyForTheAdmin(): void
    {
        $this->post(['view' => 'grid', 'changes' => '0']);

        self::assertSame(200, $this->responseCode());
        self::assertSame(['view' => ResultView::Grid, 'changesOnly' => false], $this->storage()->load());

        $this->resetRequest();
        $this->post(['view' => 'timeline', 'changes' => '1']);
        self::assertSame(['view' => ResultView::Timeline, 'changesOnly' => true], $this->storage()->load());
    }

    public function testRefusesAViewOrSwitchItDoesNotKnow(): void
    {
        $this->post(['view' => 'board', 'changes' => '1']);
        self::assertSame(400, $this->responseCode());

        $this->resetRequest();
        $this->post(['view' => 'grid', 'changes' => 'yes']);
        self::assertSame(400, $this->responseCode());

        $body = $this->getResponse();
        self::assertInstanceOf(\Magento\TestFramework\Response::class, $body);
        self::assertSame(
            ['error' => 'This view choice cannot be remembered.'],
            json_decode((string) $body->getBody(), true)
        );
        self::assertSame(['view' => null, 'changesOnly' => null], $this->storage()->load(), 'nothing is kept');
    }

    public function testRefusesARequestWithoutTheFormKey(): void
    {
        $this->post(['view' => 'grid', 'changes' => '0', 'form_key' => 'not-the-form-key']);

        self::assertNotSame(200, $this->responseCode());
        self::assertSame(['view' => null, 'changesOnly' => null], $this->storage()->load(), 'nothing is kept');
    }

    /**
     * @param array<string, string> $values
     * @return void
     */
    private function post(array $values): void
    {
        $request = $this->getRequest();
        self::assertInstanceOf(HttpRequest::class, $request);
        $request->setMethod(HttpRequest::METHOD_POST);
        $request->setHeaders(\Laminas\Http\Headers::fromString('X-Requested-With: XMLHttpRequest'));
        $request->setPostValue($values);
        $this->dispatch($this->uri);
    }

    /**
     * @return int
     */
    private function responseCode(): int
    {
        $response = $this->getResponse();
        self::assertInstanceOf(\Magento\TestFramework\Response::class, $response);

        return $response->getHttpResponseCode();
    }

    /**
     * @return SearchPreferenceStorageInterface
     */
    private function storage(): SearchPreferenceStorageInterface
    {
        return SearchFixture::service(SearchPreferenceStorageInterface::class);
    }
}
