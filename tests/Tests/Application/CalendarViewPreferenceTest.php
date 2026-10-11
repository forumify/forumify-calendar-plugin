<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use Doctrine\Common\Collections\ArrayCollection;
use Forumify\Calendar\Service\CalendarViewPreference;
use Forumify\Testing\Factories\Core\RoleFactory;
use Forumify\Testing\Factories\Core\UserFactory;
use PluginTests\Tests\Factories\CalendarFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DomCrawler\Crawler;
use Zenstruck\Foundry\Test\Factories;

class CalendarViewPreferenceTest extends WebTestCase
{
    use Factories;

    public function testWithoutRememberedViewModeTheMonthViewFollowsTheScreen(): void
    {
        $client = $this->createCalendarClient();

        $crawler = $client->request('GET', '/calendar');

        self::assertSame('month', $this->activeViewMode($crawler));
        self::assertCount(1, $crawler->filter('[data-controller~="forumify--forumify-calendar-plugin--view-mode"]'));
    }

    public function testRememberedViewModeIsUsed(): void
    {
        $client = $this->createCalendarClient();
        $client->getCookieJar()->set(new Cookie(CalendarViewPreference::COOKIE, 'week'));

        $crawler = $client->request('GET', '/calendar');

        self::assertSame('week', $this->activeViewMode($crawler));
        self::assertCount(0, $crawler->filter('[data-controller~="forumify--forumify-calendar-plugin--view-mode"]'));
    }

    public function testUnknownRememberedViewModeIsIgnored(): void
    {
        $client = $this->createCalendarClient();
        $client->getCookieJar()->set(new Cookie(CalendarViewPreference::COOKIE, 'year'));

        $crawler = $client->request('GET', '/calendar');

        self::assertSame('month', $this->activeViewMode($crawler));
    }

    public function testSwitchingViewModeIsRemembered(): void
    {
        $client = $this->createCalendarClient();
        $crawler = $client->request('GET', '/calendar');

        $component = $crawler->filterXPath('//*[@data-live-name-value="Forumify\\Calendar"]');
        $client->request(
            'POST',
            '/_components/Forumify%5CCalendar/setViewMode',
            ['data' => json_encode([
                'props' => json_decode((string)$component->attr('data-live-props-value'), true),
                'args' => ['viewMode' => 'list'],
            ])],
            server: [
                'HTTP_ACCEPT' => 'application/vnd.live-component+html',
                'HTTP_X_CSRF_TOKEN' => (string)$component->attr('data-live-csrf-value'),
            ],
        );
        self::assertResponseIsSuccessful();
        self::assertResponseHasCookie(CalendarViewPreference::COOKIE);
        self::assertSame('list', $client->getCookieJar()->get(CalendarViewPreference::COOKIE)?->getValue());

        $crawler = $client->request('GET', '/calendar');
        self::assertSame('list', $this->activeViewMode($crawler));
    }

    private function createCalendarClient(): KernelBrowser
    {
        $client = self::createClient();
        CalendarFactory::createOne();
        $client->loginUser(UserFactory::createOne([
            'roleEntities' => new ArrayCollection([
                RoleFactory::findOrCreate(['slug' => 'super-admin']),
            ]),
        ]));

        return $client;
    }

    private function activeViewMode(Crawler $crawler): string
    {
        return strtolower($crawler->filter('button[aria-pressed="true"]')->text());
    }
}
