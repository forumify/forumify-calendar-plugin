<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use DateTime;
use Forumify\Calendar\Entity\Calendar;
use Forumify\Calendar\Entity\CalendarEvent;
use Forumify\Calendar\Entity\CalendarReminder;
use Forumify\Calendar\Notification\EventReminderNotificationType;
use Forumify\Calendar\Repository\CalendarReminderRepository;
use Forumify\Calendar\Scheduler\SendEventRemindersTask;
use Forumify\Core\Entity\Notification;
use Forumify\Core\Entity\User;
use Forumify\Core\Notification\NotificationMessage;
use Forumify\Core\Repository\NotificationRepository;
use Forumify\Testing\Factories\Core\RoleFactory;
use Forumify\Testing\Traits\SettingTrait;
use PluginTests\Tests\Factories\CalendarEventFactory;
use PluginTests\Tests\Factories\CalendarFactory;
use PluginTests\Tests\Traits\CalendarAccessTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Mime\Email;
use Zenstruck\Foundry\Test\Factories;

class SendEventRemindersTaskTest extends KernelTestCase
{
    use CalendarAccessTrait;
    use ClockSensitiveTrait;
    use Factories;
    use SettingTrait;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = self::mockTime('2030-06-15 12:00:00');
        // the first run only remembers where to start from
        $this->runTask();
    }

    public function testRemindsBeforeTheEventStarts(): void
    {
        $calendar = CalendarFactory::createOne();
        $user = $this->createUserThatCanView($calendar);
        $this->addReminder($user, $calendar, 15);
        $event = $this->createEvent($calendar, '2030-06-15 12:16:00');

        $this->clock->modify('+1 minute');
        $this->runTask();

        $notifications = $this->findReminders($user);
        self::assertCount(1, $notifications);
        $context = $notifications[0]->getDeserializedContext();
        self::assertSame($event->getId(), $context['event']->getId());
        self::assertSame('2030-06-15T12:16:00+00:00', $context['occurrence']);
        self::assertSame(15, $context['minutesBefore']);

        $sent = $this->getSentNotificationMessages();
        self::assertCount(1, $sent);
        self::assertSame([$notifications[0]->getId()], $sent[0]->notificationId);
        self::assertTrue($sent[0]->ignoreIsOnline, 'reminder emails are not delayed for online users');
    }

    public function testEachReminderIsOnlySentOnce(): void
    {
        $calendar = CalendarFactory::createOne();
        $user = $this->createUserThatCanView($calendar);
        $this->addReminder($user, $calendar, 15);
        $this->createEvent($calendar, '2030-06-15 12:16:00');

        for ($i = 0; $i < 5; $i++) {
            $this->clock->modify('+1 minute');
            $this->runTask();
            // a replayed run at the same time finds an empty window
            $this->runTask();
        }

        self::assertCount(1, $this->findReminders($user));
    }

    public function testUsesEveryReminderOffset(): void
    {
        $calendar = CalendarFactory::createOne();
        $user = $this->createUserThatCanView($calendar);
        $this->addReminder($user, $calendar, 15);
        $this->addReminder($user, $calendar, 60);
        $this->createEvent($calendar, '2030-06-15 13:05:00');

        // the 1 hour reminder is due at 12:05
        $this->clock->modify('+15 minutes');
        $this->runTask();
        // the 15 minute reminder is due at 12:50
        $this->clock->modify('+40 minutes');
        $this->runTask();

        $minutesBefore = array_map(
            fn (Notification $n) => $n->getDeserializedContext()['minutesBefore'],
            $this->findReminders($user),
        );
        sort($minutesBefore);
        self::assertSame([15, 60], $minutesBefore);
    }

    public function testCatchesUpAfterDowntimeButSkipsEventsThatStarted(): void
    {
        $calendar = CalendarFactory::createOne();
        $user = $this->createUserThatCanView($calendar);
        $this->addReminder($user, $calendar, 15);
        $this->createEvent($calendar, '2030-06-15 12:20:00', 'Already Started');
        $this->createEvent($calendar, '2030-06-15 12:40:00', 'Reminded Late');
        $this->createEvent($calendar, '2030-06-15 12:50:00', 'Not Due Yet');

        // the worker was down for half an hour
        $this->clock->modify('+30 minutes');
        $this->runTask();

        $titles = array_map(
            fn (Notification $n) => $n->getDeserializedContext()['event']->getTitle(),
            $this->findReminders($user),
        );
        self::assertSame(['Reminded Late'], $titles);
    }

    public function testRemindsForEveryOccurrenceOfARepeatingEvent(): void
    {
        $calendar = CalendarFactory::createOne();
        $user = $this->createUserThatCanView($calendar);
        $this->addReminder($user, $calendar, 15);
        $event = $this->createEvent($calendar, '2030-06-01 12:16:00');
        $event->setRepeat('daily');
        $this->flush();

        $this->clock->modify('+1 minute');
        $this->runTask();
        $this->clock->modify('+1 day');
        $this->runTask();

        $occurrences = array_map(
            fn (Notification $n) => $n->getDeserializedContext()['occurrence'],
            $this->findReminders($user),
        );
        sort($occurrences);
        self::assertSame(['2030-06-15T12:16:00+00:00', '2030-06-16T12:16:00+00:00'], $occurrences);
    }

    public function testMovedEventIsRemindedAtItsNewTime(): void
    {
        $calendar = CalendarFactory::createOne();
        $user = $this->createUserThatCanView($calendar);
        $this->addReminder($user, $calendar, 15);
        $event = $this->createEvent($calendar, '2030-06-15 18:00:00');

        $event->setStart(new DateTime('2030-06-15 12:16:00'));
        $this->flush();

        $this->clock->modify('+1 minute');
        $this->runTask();

        self::assertCount(1, $this->findReminders($user));
    }

    public function testOnlyRemindsForTheCalendarAndOffsetOfTheReminder(): void
    {
        $calendar = CalendarFactory::createOne();
        $otherCalendar = CalendarFactory::createOne();
        $user = $this->createUserWithRole($this->createRoleThatCanView($calendar, $otherCalendar));
        $this->addReminder($user, $calendar, 60);
        $this->createEvent($calendar, '2030-06-15 12:16:00');
        $this->createEvent($otherCalendar, '2030-06-15 13:01:00');

        $this->clock->modify('+1 minute');
        $this->runTask();

        self::assertEmpty($this->findReminders($user));
    }

    public function testUsersThatCanNoLongerViewTheCalendarAreNotReminded(): void
    {
        $calendar = CalendarFactory::createOne();
        $user = $this->createUserWithRole(RoleFactory::createOne());
        $this->addReminder($user, $calendar, 15);
        $this->createEvent($calendar, '2030-06-15 12:16:00');

        $this->clock->modify('+1 minute');
        $this->runTask();

        self::assertEmpty($this->findReminders($user));
        self::assertEmpty($this->getSentNotificationMessages());
    }

    public function testReminderEmailShowsTheOccurrenceInTheRecipientsTimezone(): void
    {
        $this->setSetting('forumify.mailer.from', 'calendar@example.org');

        $calendar = CalendarFactory::createOne();
        $user = $this->createUserThatCanView($calendar);
        $user->setTimezone('Asia/Tokyo');
        $this->addReminder($user, $calendar, 15);
        $event = $this->createEvent($calendar, '2030-06-01 12:16:00', 'Daily Standup');
        $event->setRepeat('daily');
        $event->setEnd(new DateTime('2030-06-01 12:46:00'));
        $this->flush();

        $this->clock->modify('+1 minute');
        $this->runTask();

        [$notification] = $this->findReminders($user);
        $type = self::getContainer()->get(EventReminderNotificationType::class);
        self::assertSame('Saturday, June 15, 2030 9:16 PM', $type->getDescription($notification));

        $type->handleNotification($notification);

        self::assertEmailCount(1);
        /** @var Email $email */
        $email = self::getMailerMessage();
        self::assertSame('"Daily Standup" starts in 15 minutes', $email->getSubject());
        self::assertEmailHtmlBodyContains($email, 'Saturday, June 15, 2030 9:16 PM');
        self::assertEmailHtmlBodyContains($email, '9:46 PM');
        self::assertEmailHtmlBodyContains($email, 'Asia/Tokyo');
    }

    private function runTask(): void
    {
        self::getContainer()->get(SendEventRemindersTask::class)();
    }

    private function createUserThatCanView(Calendar $calendar): User
    {
        return $this->createUserWithRole($this->createRoleThatCanView($calendar));
    }

    private function addReminder(User $user, Calendar $calendar, int $minutesBefore): void
    {
        self::getContainer()
            ->get(CalendarReminderRepository::class)
            ->save(new CalendarReminder($user, $calendar, $minutesBefore));
    }

    private function createEvent(Calendar $calendar, string $start, string $title = 'Event'): CalendarEvent
    {
        return CalendarEventFactory::createOne([
            'calendar' => $calendar,
            'start' => new DateTime($start),
            'title' => $title,
        ]);
    }

    private function flush(): void
    {
        self::getContainer()->get('doctrine')->getManager()->flush();
    }

    /**
     * @return array<Notification>
     */
    private function findReminders(User $user): array
    {
        return self::getContainer()->get(NotificationRepository::class)->findBy([
            'recipient' => $user,
            'type' => EventReminderNotificationType::TYPE,
        ]);
    }

    /**
     * @return list<NotificationMessage>
     */
    private function getSentNotificationMessages(): array
    {
        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.async');

        $messages = [];
        foreach ($transport->getSent() as $envelope) {
            if ($envelope->getMessage() instanceof NotificationMessage) {
                $messages[] = $envelope->getMessage();
            }
        }
        return $messages;
    }
}
