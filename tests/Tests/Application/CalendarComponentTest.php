<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use DateInterval;
use DateTime;
use DateTimeZone;
use Doctrine\Common\Collections\ArrayCollection;
use Forumify\Calendar\Entity\Calendar;
use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use PluginTests\Tests\Factories\ACLFactory;
use PluginTests\Tests\Factories\CalendarEventFactory;
use PluginTests\Tests\Factories\CalendarFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Tests\Tests\Factories\Core\RoleFactory;
use Tests\Tests\Factories\Core\UserFactory;
use Zenstruck\Foundry\Test\Factories;

class CalendarComponentTest extends WebTestCase
{
    use Factories;
    use InteractsWithLiveComponents;

    public function testShowsEventsInCurrentMonth(): void
    {
        $calendar = CalendarFactory::createOne();
        CalendarEventFactory::createOne([
            'calendar' => $calendar,
            'title' => 'Midmonth Meetup',
            'start' => $this->dayOfThisMonth(15),
        ]);

        $render = $this->createLiveComponent('Forumify\Calendar', ['selectedCalendar' => $calendar])
            ->actingAs($this->createSuperAdmin())
            ->render();

        self::assertStringContainsString('Midmonth Meetup', (string)$render);
        self::assertStringContainsString(new DateTime()->format('F Y'), (string)$render);
    }

    public function testNavigatingToNextAndPreviousMonth(): void
    {
        $calendar = CalendarFactory::createOne();
        CalendarEventFactory::createOne([
            'calendar' => $calendar,
            'title' => 'This Month Event',
            'start' => $this->dayOfThisMonth(15),
        ]);
        CalendarEventFactory::createOne([
            'calendar' => $calendar,
            // the month grid is always 6 rows, so it can spill up to 14 days into the next month
            'title' => 'Next Month Event',
            'start' => $this->dayOfThisMonth(1)->modify('first day of next month')->modify('+19 days'),
        ]);

        $component = $this->createLiveComponent('Forumify\Calendar', ['selectedCalendar' => $calendar])
            ->actingAs($this->createSuperAdmin());

        $render = (string)$component->render();
        self::assertStringContainsString('This Month Event', $render);
        self::assertStringNotContainsString('Next Month Event', $render);

        $render = (string)$component->call('next')->render();
        self::assertStringContainsString('Next Month Event', $render);
        self::assertStringNotContainsString('This Month Event', $render);

        $render = (string)$component->call('prev')->render();
        self::assertStringContainsString('This Month Event', $render);
        self::assertStringNotContainsString('Next Month Event', $render);
    }

    public function testToggleViewModeShowsCurrentWeek(): void
    {
        $calendar = CalendarFactory::createOne();
        CalendarEventFactory::createOne([
            'calendar' => $calendar,
            'title' => 'Happening Today',
            'start' => new DateTime('today 12:00'),
        ]);

        $component = $this->createLiveComponent('Forumify\Calendar', ['selectedCalendar' => $calendar])
            ->actingAs($this->createSuperAdmin());

        self::assertEquals('month', $component->component()->viewMode);

        $render = (string)$component->call('toggleViewMode')->render();

        self::assertEquals('week', $component->component()->viewMode);
        self::assertStringContainsString('Happening Today', $render);
    }

    public function testRecurringEventRepeatsOnEveryDayOfTheMonth(): void
    {
        $calendar = CalendarFactory::createOne();
        CalendarEventFactory::createOne([
            'calendar' => $calendar,
            'title' => 'Daily Standup',
            'start' => $this->dayOfThisMonth(1)->setTime(9, 0),
            'repeat' => 'daily',
            'repeatEnd' => $this->dayOfThisMonth(1)->modify('last day of this month'),
        ]);

        $crawler = $this->createLiveComponent('Forumify\Calendar', ['selectedCalendar' => $calendar])
            ->actingAs($this->createSuperAdmin())
            ->render()
            ->crawler();

        $daysWithStandup = $crawler
            ->filter('.calendar-day')
            ->reduce(fn (Crawler $day) => str_contains($day->text(), 'Daily Standup'))
            ->count();

        // repeats every day until the end of the month, the grid also shows adjacent months
        self::assertSame((int)new DateTime()->format('t'), $daysWithStandup);
    }

    public function testOnlyShowsEventsFromSelectedCalendar(): void
    {
        $selected = CalendarFactory::createOne();
        $other = CalendarFactory::createOne();

        CalendarEventFactory::createOne([
            'calendar' => $selected,
            'title' => 'Selected Calendar Event',
            'start' => $this->dayOfThisMonth(15),
        ]);
        CalendarEventFactory::createOne([
            'calendar' => $other,
            'title' => 'Other Calendar Event',
            'start' => $this->dayOfThisMonth(15),
        ]);

        $render = (string)$this->createLiveComponent('Forumify\Calendar', ['selectedCalendar' => $selected])
            ->actingAs($this->createSuperAdmin())
            ->render();

        self::assertStringContainsString('Selected Calendar Event', $render);
        self::assertStringNotContainsString('Other Calendar Event', $render);
    }

    public function testEventsAreHiddenWithoutViewPermission(): void
    {
        $calendar = CalendarFactory::createOne();
        CalendarEventFactory::createOne([
            'calendar' => $calendar,
            'title' => 'Members Only',
            'start' => $this->dayOfThisMonth(15),
        ]);

        $role = RoleFactory::createOne();
        $user = UserFactory::createOne(['roleEntities' => new ArrayCollection([$role])]);

        $render = (string)$this->createLiveComponent('Forumify\Calendar', ['selectedCalendar' => $calendar])
            ->actingAs($user)
            ->render();
        self::assertStringNotContainsString('Members Only', $render);

        $this->grantView($calendar, $role);

        $render = (string)$this->createLiveComponent('Forumify\Calendar', ['selectedCalendar' => $calendar])
            ->actingAs($user)
            ->render();
        self::assertStringContainsString('Members Only', $render);
    }

    public function testTimezoneNoticeOnlyPromptsGuestsToLogIn(): void
    {
        $calendar = CalendarFactory::createOne();

        $guestRender = (string)$this->createLiveComponent('Forumify\Calendar', ['selectedCalendar' => $calendar])
            ->render();

        self::assertStringContainsString('Times are shown in UTC.', $guestRender);
        self::assertStringContainsString('to see them in your own timezone', $guestRender);
        self::assertStringContainsString(
            'href="' . $this->loginPathFor($calendar) . '"',
            html_entity_decode($guestRender),
        );

        $userRender = (string)$this->createLiveComponent('Forumify\Calendar', ['selectedCalendar' => $calendar])
            ->actingAs($this->createSuperAdmin())
            ->render();

        self::assertStringContainsString('Times are shown in UTC.', $userRender);
        self::assertStringNotContainsString('to see them in your own timezone', $userRender);
    }

    private function loginPathFor(Calendar $calendar): string
    {
        $router = self::getContainer()->get('router');

        return $router->generate('forumify_core_login', [
            '_target_path' => $router->generate('forumify_calendar_all', ['slug' => $calendar->getSlug()]),
        ]);
    }

    public function testEventTimesUseTheUsersTimezone(): void
    {
        // stored as-is by doctrine, so it is read back in whatever timezone php defaults to
        $start = $this->dayOfThisMonth(15)->setTime(12, 0);

        $calendar = CalendarFactory::createOne();
        CalendarEventFactory::createOne([
            'calendar' => $calendar,
            'title' => 'Timezone Check',
            'start' => $start,
        ]);

        $tokyo = $this->renderTimeForUserTimezone($calendar, 'Asia/Tokyo');
        $honolulu = $this->renderTimeForUserTimezone($calendar, 'Pacific/Honolulu');

        self::assertStringContainsString(
            DateTime::createFromInterface($start)->setTimezone(new DateTimeZone('Asia/Tokyo'))->format('H:i'),
            $tokyo,
        );
        self::assertStringContainsString(
            DateTime::createFromInterface($start)->setTimezone(new DateTimeZone('Pacific/Honolulu'))->format('H:i'),
            $honolulu,
        );
    }

    private function renderTimeForUserTimezone(Calendar $calendar, string $timezone): string
    {
        $user = $this->createSuperAdmin();
        $user->setTimezone($timezone);
        self::getContainer()->get('doctrine')->getManager()->flush();

        return (string)$this->createLiveComponent('Forumify\Calendar', ['selectedCalendar' => $calendar])
            ->actingAs($user)
            ->render();
    }

    private function createSuperAdmin(): User
    {
        return UserFactory::createOne([
            'roleEntities' => new ArrayCollection([
                RoleFactory::findOrCreate(['slug' => 'super-admin']),
            ]),
        ]);
    }

    private function grantView(Calendar $calendar, Role $role): void
    {
        ACLFactory::createOne([
            'entity' => Calendar::class,
            'entityId' => (string)$calendar->getId(),
            'permission' => 'view',
            'roles' => [$role],
        ]);
    }

    private function dayOfThisMonth(int $day): DateTime
    {
        return new DateTime('first day of this month 12:00')
            ->add(new DateInterval('P' . ($day - 1) . 'D'));
    }
}
