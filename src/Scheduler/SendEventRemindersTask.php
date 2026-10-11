<?php

declare(strict_types=1);

namespace Forumify\Calendar\Scheduler;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\Calendar\Entity\CalendarEvent;
use Forumify\Calendar\Entity\CalendarReminder;
use Forumify\Calendar\Entity\CalendarReminderCursor;
use Forumify\Calendar\Notification\EventReminderNotificationType;
use Forumify\Calendar\Repository\CalendarEventRepository;
use Forumify\Calendar\Repository\CalendarReminderRepository;
use Forumify\Calendar\Service\EventRecurrence;
use Forumify\Core\Entity\Notification;
use Forumify\Core\Notification\NotificationMessage;
use Forumify\Core\Repository\NotificationRepository;
use Forumify\Core\Security\VoterAttribute;
use Forumify\Core\Service\TimezoneResolver;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

/**
 * Sends reminders for event occurrences that start within a reminder's "minutes before".
 *
 * Every run handles the time that passed since the previous run, so the amount of work depends on elapsed time
 * rather than on the number of runs. Runs the scheduler replays after a slow run find an empty window.
 */
#[AsPeriodicTask('1 minute')]
class SendEventRemindersTask
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CalendarReminderRepository $reminderRepository,
        private readonly CalendarEventRepository $eventRepository,
        private readonly NotificationRepository $notificationRepository,
        private readonly EventRecurrence $eventRecurrence,
        private readonly TimezoneResolver $timezoneResolver,
        private readonly Security $security,
        private readonly MessageBusInterface $messageBus,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(): void
    {
        // doctrine reads and writes datetimes in PHP's default timezone
        $now = $this->clock->now()->setTimezone(new DateTimeZone(date_default_timezone_get()));

        $this->entityManager->wrapInTransaction(function () use ($now): void {
            $cursor = $this->entityManager->find(CalendarReminderCursor::class, CalendarReminderCursor::ID);
            if ($cursor === null) {
                // first run, start sending reminders from now on
                $this->entityManager->persist(new CalendarReminderCursor($now));
                return;
            }

            // the row lock makes concurrent runs wait, they'll find an empty window once this one commits.
            // refresh rather than find with a lock mode, which doesn't re-read an entity that is already loaded.
            $this->entityManager->refresh($cursor, LockMode::PESSIMISTIC_WRITE);

            $processedUntil = $cursor->getProcessedUntil();
            if ($processedUntil >= $now) {
                return;
            }

            $notifications = $this->createNotifications($processedUntil, $now);
            $cursor->setProcessedUntil($now);
            $this->notificationRepository->saveAll($notifications);

            if (!empty($notifications)) {
                // reminders are time sensitive, so don't hold back the email when the recipient is online
                $this->messageBus->dispatch(new NotificationMessage(
                    array_map(fn (Notification $n) => $n->getId(), $notifications),
                    ignoreIsOnline: true,
                ));
            }
        });
    }

    /**
     * @return list<Notification>
     */
    private function createNotifications(DateTimeImmutable $processedUntil, DateTimeImmutable $now): array
    {
        // there is no logged-in user here, so this is the forum's default timezone
        $timezone = $this->timezoneResolver->getTimezone();

        $notifications = [];
        foreach ($this->reminderRepository->findCalendarIdsByMinutesBefore() as $minutesBefore => $calendarIds) {
            // occurrences that already started are skipped, this matters when catching up after downtime
            $after = max($processedUntil->modify("+$minutesBefore minutes"), $now);
            $until = $now->modify("+$minutesBefore minutes");

            $occurrences = $this->findOccurrences($calendarIds, $after, $until, $timezone);
            if (empty($occurrences)) {
                continue;
            }

            /** @var array<CalendarReminder> $reminders */
            $reminders = $this->reminderRepository->findBy([
                'calendar' => array_unique(array_map(fn (array $o) => $o['event']->getCalendar()->getId(), $occurrences)),
                'minutesBefore' => $minutesBefore,
            ]);

            foreach ($occurrences as ['event' => $event, 'start' => $start]) {
                foreach ($reminders as $reminder) {
                    if ($reminder->getCalendar()->getId() !== $event->getCalendar()->getId()) {
                        continue;
                    }

                    // reminders don't check permissions when they're created, and they may have been revoked since
                    $canView = $this->security->isGrantedForUser($reminder->getUser(), VoterAttribute::ACL->value, [
                        'entity' => $event->getCalendar(),
                        'permission' => 'view',
                    ]);
                    if (!$canView) {
                        continue;
                    }

                    $notifications[] = new Notification(EventReminderNotificationType::TYPE, $reminder->getUser(), [
                        'event' => $event,
                        'occurrence' => $start->format(DateTimeInterface::ATOM),
                        'minutesBefore' => $minutesBefore,
                    ]);
                }
            }
        }

        return $notifications;
    }

    /**
     * @param list<int> $calendarIds
     * @return list<array{event: CalendarEvent, start: DateTime}>
     */
    private function findOccurrences(
        array $calendarIds,
        DateTimeImmutable $after,
        DateTimeImmutable $until,
        DateTimeZone $timezone,
    ): array {
        $events = $this->eventRepository->findPossiblyStartingBetween(
            $calendarIds,
            DateTime::createFromImmutable($after),
            DateTime::createFromImmutable($until),
        );

        $occurrences = [];
        foreach ($events as $event) {
            foreach ($this->eventRecurrence->getOccurrences($event, $after, $until, $timezone) as $start) {
                $occurrences[] = ['event' => $event, 'start' => $start];
            }
        }
        return $occurrences;
    }
}
