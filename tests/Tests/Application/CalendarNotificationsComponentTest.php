<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use Forumify\Calendar\Entity\Calendar;
use Forumify\Calendar\Notification\EventCreatedNotificationType;
use Forumify\Calendar\Repository\CalendarReminderRepository;
use Forumify\Core\Entity\User;
use Forumify\Forum\Repository\SubscriptionRepository;
use Forumify\Testing\Factories\Core\RoleFactory;
use PluginTests\Tests\Factories\CalendarFactory;
use PluginTests\Tests\Traits\CalendarAccessTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;
use Zenstruck\Foundry\Test\Factories;

class CalendarNotificationsComponentTest extends WebTestCase
{
    use CalendarAccessTrait;
    use Factories;
    use InteractsWithLiveComponents;

    public function testGuestsAreAskedToLogIn(): void
    {
        $calendar = CalendarFactory::createOne();

        $crawler = $this->createLiveComponent('Forumify\CalendarNotifications', ['calendar' => $calendar])
            ->render()
            ->crawler();

        self::assertStringContainsString('/login', (string)$crawler->filter('a')->attr('href'));
        self::assertCount(0, $crawler->filter('button[data-live-action-param]'));
    }

    public function testToggleSubscription(): void
    {
        $calendar = CalendarFactory::createOne();
        $user = $this->createUserWithRole($this->createRoleThatCanView($calendar));
        $component = $this->createLiveComponent('Forumify\CalendarNotifications', ['calendar' => $calendar])
            ->actingAs($user);

        $component->call('toggleSubscription');
        self::assertTrue($this->isSubscribed($user, $calendar));
        self::assertStringContainsString('ph-bell-ringing', (string)$component->render());

        $component->call('toggleSubscription');
        self::assertFalse($this->isSubscribed($user, $calendar));
        self::assertStringNotContainsString('ph-bell-ringing', (string)$component->render());
    }

    public function testToggleReminders(): void
    {
        $calendar = CalendarFactory::createOne();
        $user = $this->createUserWithRole($this->createRoleThatCanView($calendar));
        $component = $this->createLiveComponent('Forumify\CalendarNotifications', ['calendar' => $calendar])
            ->actingAs($user);

        $component->call('toggleReminder', ['minutesBefore' => 15]);
        $component->call('toggleReminder', ['minutesBefore' => 1440]);
        self::assertSame([15, 1440], $this->getReminderMinutes($user, $calendar));

        self::assertSame([
            'New events' => 'false',
            '15 minutes before' => 'true',
            '1 hour before' => 'false',
            '1 day before' => 'true',
        ], $this->checkboxes($component));

        $component->call('toggleReminder', ['minutesBefore' => 15]);
        self::assertSame([1440], $this->getReminderMinutes($user, $calendar));
    }

    public function testOnlyOffersFixedReminderOffsets(): void
    {
        $calendar = CalendarFactory::createOne();
        $user = $this->createUserWithRole($this->createRoleThatCanView($calendar));
        $component = $this->createLiveComponent('Forumify\CalendarNotifications', ['calendar' => $calendar])
            ->actingAs($user);

        $this->expectExceptionMessage('Reminders can not be sent 1 minutes before an event.');
        $component->call('toggleReminder', ['minutesBefore' => 1]);
    }

    public function testCanNotSetUpNotificationsWithoutViewPermission(): void
    {
        $calendar = CalendarFactory::createOne();
        $user = $this->createUserWithRole(RoleFactory::createOne());
        $component = $this->createLiveComponent('Forumify\CalendarNotifications', ['calendar' => $calendar])
            ->actingAs($user);

        foreach ([['toggleSubscription', []], ['toggleReminder', ['minutesBefore' => 15]]] as [$action, $args]) {
            try {
                $component->call($action, $args);
                self::fail("$action should be denied");
            } catch (\Throwable $e) {
                self::assertStringContainsString('Access Denied', $e->getMessage());
            }
        }

        self::assertFalse($this->isSubscribed($user, $calendar));
        self::assertSame([], $this->getReminderMinutes($user, $calendar));
    }

    public function testAllCalendarsShowWhetherAnyCalendarHasNotifications(): void
    {
        [$first, $second] = CalendarFactory::createMany(2);
        $user = $this->createUserWithRole($this->createRoleThatCanView($first, $second));

        $this->createLiveComponent('Forumify\CalendarNotifications', ['calendar' => $first])
            ->actingAs($user)
            ->call('toggleSubscription')
            ->call('toggleReminder', ['minutesBefore' => 60]);
        $this->createLiveComponent('Forumify\CalendarNotifications', ['calendar' => $second])
            ->actingAs($user)
            ->call('toggleReminder', ['minutesBefore' => 60]);

        $component = $this->createLiveComponent('Forumify\CalendarNotifications')->actingAs($user);
        self::assertSame([
            'New events' => 'mixed',
            '15 minutes before' => 'false',
            '1 hour before' => 'true',
            '1 day before' => 'false',
        ], $this->checkboxes($component));
        self::assertStringContainsString('ph-bell-ringing', (string)$component->render());
    }

    public function testAllCalendarsEnablesForEveryViewableCalendar(): void
    {
        [$first, $second, $hidden] = CalendarFactory::createMany(3);
        $user = $this->createUserWithRole($this->createRoleThatCanView($first, $second));
        $component = $this->createLiveComponent('Forumify\CalendarNotifications')->actingAs($user);

        $component->call('toggleSubscription');
        $component->call('toggleReminder', ['minutesBefore' => 15]);

        foreach ([$first, $second] as $calendar) {
            self::assertTrue($this->isSubscribed($user, $calendar));
            self::assertSame([15], $this->getReminderMinutes($user, $calendar));
        }
        self::assertFalse($this->isSubscribed($user, $hidden));
        self::assertSame([], $this->getReminderMinutes($user, $hidden));
        self::assertSame('true', $this->checkboxes($component)['New events']);
    }

    public function testAllCalendarsDisablesAPartiallyEnabledSetting(): void
    {
        [$first, $second] = CalendarFactory::createMany(2);
        $user = $this->createUserWithRole($this->createRoleThatCanView($first, $second));
        $this->createLiveComponent('Forumify\CalendarNotifications', ['calendar' => $first])
            ->actingAs($user)
            ->call('toggleSubscription')
            ->call('toggleReminder', ['minutesBefore' => 1440]);

        $component = $this->createLiveComponent('Forumify\CalendarNotifications')->actingAs($user);
        $component->call('toggleSubscription');
        $component->call('toggleReminder', ['minutesBefore' => 1440]);

        self::assertFalse($this->isSubscribed($user, $first));
        self::assertFalse($this->isSubscribed($user, $second));
        self::assertSame([], $this->getReminderMinutes($user, $first));
        self::assertSame(
            ['New events' => 'false', '15 minutes before' => 'false', '1 hour before' => 'false', '1 day before' => 'false'],
            $this->checkboxes($component),
        );
        self::assertStringNotContainsString('ph-bell-ringing', (string)$component->render());
    }

    /**
     * @return array<string, string> the aria-checked state of each checkbox, keyed by label
     */
    private function checkboxes(TestLiveComponent $component): array
    {
        $checkboxes = [];
        $component->render()->crawler()->filter('[role="checkbox"]')->each(function (Crawler $checkbox) use (&$checkboxes) {
            $checkboxes[$checkbox->text()] = (string)$checkbox->attr('aria-checked');
        });
        return $checkboxes;
    }

    private function isSubscribed(User $user, Calendar $calendar): bool
    {
        return self::getContainer()->get(SubscriptionRepository::class)->count([
            'user' => $user,
            'type' => EventCreatedNotificationType::TYPE,
            'subjectId' => $calendar->getId(),
        ]) > 0;
    }

    /**
     * @return list<int>
     */
    private function getReminderMinutes(User $user, Calendar $calendar): array
    {
        $reminders = self::getContainer()->get(CalendarReminderRepository::class)->findBy(
            ['user' => $user, 'calendar' => $calendar],
            ['minutesBefore' => 'ASC'],
        );

        return array_map(fn ($reminder) => $reminder->getMinutesBefore(), $reminders);
    }
}
