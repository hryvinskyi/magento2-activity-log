<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Controller;

use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\SearchFixture;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Route\ConfigInterface as RouteConfig;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * With "Add Secret Key to URLs" on, the links of the Activity Log menu open their pages
 *
 * The admin router checks a link's key against the key of the request's route id, controller and action; a link
 * built from the URL's front name carries another key and is answered with a redirect to the admin start page.
 * Each test dispatches once: the test application serves a second dispatch of one test from the first one's state.
 */
#[DbIsolation(true)]
class MenuSecretKeyTest extends AbstractBackendController
{
    /**
     * The module's admin route id and its URL front name
     */
    private const ROUTE_ID = 'hryvinskyi_activitylog';
    private const FRONT_NAME = 'activitylog';

    /**
     * The inherited access tests open the activity page, through a link carrying its key
     *
     * @var string
     */
    protected $resource = 'Hryvinskyi_ActivityLog::view';

    protected function setUp(): void
    {
        parent::setUp();
        $this->urlBuilder()->turnOnSecretKey();
        $this->uri = $this->path($this->urlBuilder()->getUrl(self::ROUTE_ID . '/activity/index'));
    }

    public function testEveryLinkOfTheActivityLogMenuCarriesTheKeyTheRouterAccepts(): void
    {
        $this->dispatch($this->path($this->urlBuilder()->getUrl(self::ROUTE_ID . '/dashboard/index')));
        self::assertSame(200, $this->responseCode(), 'the dashboard opens with its secret key');

        $links = $this->menuLinks($this->body());
        self::assertGreaterThanOrEqual(2, count($links), 'the menu links the dashboard and the activity page');
        self::assertSame(
            self::ROUTE_ID,
            SearchFixture::service(RouteConfig::class)->getRouteByFrontName(self::FRONT_NAME, 'adminhtml')
        );

        foreach ($links as $link) {
            $found = preg_match(
                '#/' . self::FRONT_NAME . '/([a-z_]+)/([a-z_]+)/key/([0-9a-f]+)/#i',
                $link,
                $parts
            );
            self::assertSame(1, $found, $link . ' is a link with a secret key');
            self::assertSame(
                $this->urlBuilder()->getSecretKey(self::ROUTE_ID, $parts[1], $parts[2]),
                $parts[3],
                $link . ' carries the key the router checks'
            );
        }
    }

    public function testALinkBuiltFromTheRouteIdOpensTheActivityPage(): void
    {
        $this->dispatch($this->path($this->urlBuilder()->getUrl(self::ROUTE_ID . '/activity/index')));

        self::assertSame(200, $this->responseCode());
        self::assertStringContainsString('data-role="search-config"', $this->body());
    }

    public function testALinkBuiltFromTheFrontNameIsSentToTheStartPage(): void
    {
        $this->dispatch($this->path($this->urlBuilder()->getUrl(self::FRONT_NAME . '/activity/index')));

        self::assertSame(302, $this->responseCode(), 'why every admin link is built from the route id');
    }

    /**
     * The links of the Activity Log menu that lead to this module's pages
     *
     * @param string $html
     * @return list<string>
     */
    private function menuLinks(string $html): array
    {
        self::assertSame(
            1,
            preg_match('#hryvinskyi-core-menu.*?<ul class="admin__action-dropdown-menu">(.*?)</ul>#s', $html, $menu),
            'the page prints the Activity Log menu'
        );
        preg_match_all('#<a href="([^"]+)"#', $menu[1] ?? '', $hrefs);

        return array_values(array_filter(
            array_map(
                static fn (string $href): string => html_entity_decode($href, ENT_QUOTES | ENT_HTML5),
                $hrefs[1]
            ),
            static fn (string $href): bool => str_contains($href, '/' . self::FRONT_NAME . '/')
        ));
    }

    /**
     * The path the test application dispatches for an absolute admin URL
     *
     * @param string $url
     * @return string
     */
    private function path(string $url): string
    {
        $start = strpos($url, 'backend/');
        self::assertNotFalse($start, $url . ' is an admin URL');

        return substr($url, $start);
    }

    /**
     * @return UrlInterface
     */
    private function urlBuilder(): UrlInterface
    {
        return SearchFixture::service(UrlInterface::class);
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
     * @return string
     */
    private function body(): string
    {
        $response = $this->getResponse();
        self::assertInstanceOf(\Magento\TestFramework\Response::class, $response);

        return (string) $response->getBody();
    }
}
