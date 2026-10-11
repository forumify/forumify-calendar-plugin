<?php

declare(strict_types=1);

namespace Forumify\Calendar\Component;

use Forumify\Calendar\Entity\Calendar;
use Forumify\Calendar\Entity\CalendarReminder;
use Forumify\Calendar\Notification\EventCreatedNotificationType;
use Forumify\Calendar\Repository\CalendarReminderRepository;
use Forumify\Calendar\Repository\CalendarRepository;
use Forumify\Core\Entity\User;
use Forumify\Core\Security\VoterAttribute;
use Forumify\Forum\Entity\Subscription;
use Forumify\Forum\Repository\SubscriptionRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Without a calendar, every setting applies to all calendars the user can view.
 */
#[AsLiveComponent('Forumify\\CalendarNotifications', '@ForumifyCalendarPlugin/frontend/components/calendar/notifications.html.twig')]
class CalendarNotificationsComponent
{
    use DefaultActionTrait;

    public const string OFF = 'off';
    public const string MIXED = 'mixed';
    public const string ON = 'on';

    #[LiveProp]
    public ?Calendar $calendar = null;

    /** @var list<int>|null */
    private ?array $calendarIds = null;
    /** @var list<int>|null */
    private ?array $subscribedCalendarIds = null;
    /** @var array<int, list<int>>|null */
    private ?array $reminderCalendarIds = null;

    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly CalendarRepository $calendarRepository,
        private readonly CalendarReminderRepository $reminderRepository,
        private readonly Security $security,
    ) {
    }

    /**
     * @return list<int>
     */
    public function getMinutesBeforeOptions(): array
    {
        return CalendarReminder::MINUTES_BEFORE_OPTIONS;
    }

    /**
     * @return self::OFF|self::MIXED|self::ON
     */
    public function getSubscriptionState(): string
    {
        return $this->getState($this->getSubscribedCalendarIds());
    }

    /**
     * @return self::OFF|self::MIXED|self::ON
     */
    public function getReminderState(int $minutesBefore): string
    {
        return $this->getState($this->getReminderCalendarIds()[$minutesBefore] ?? []);
    }

    public function isActive(): bool
    {
        if ($this->getSubscriptionState() !== self::OFF) {
            return true;
        }

        foreach ($this->getMinutesBeforeOptions() as $minutesBefore) {
            if ($this->getReminderState($minutesBefore) !== self::OFF) {
                return true;
            }
        }
        return false;
    }

    #[LiveAction]
    #[IsGranted('ROLE_USER')]
    public function toggleSubscription(): void
    {
        $user = $this->requireUser();
        if ($this->getSubscriptionState() !== self::OFF) {
            $criteria = ['user' => $user, 'type' => EventCreatedNotificationType::TYPE];
            if ($this->calendar !== null) {
                $criteria['subjectId'] = $this->calendar->getId();
            }
            $this->subscriptionRepository->removeAll($this->subscriptionRepository->findBy($criteria));
        } else {
            $this->denyAccessUnlessCanView();
            $this->subscriptionRepository->saveAll(array_map(function (int $calendarId) use ($user) {
                $subscription = new Subscription();
                $subscription->setUser($user);
                $subscription->setType(EventCreatedNotificationType::TYPE);
                $subscription->setSubjectId($calendarId);
                return $subscription;
            }, $this->getCalendarIds()));
        }

        $this->subscribedCalendarIds = null;
    }

    #[LiveAction]
    #[IsGranted('ROLE_USER')]
    public function toggleReminder(#[LiveArg] int $minutesBefore): void
    {
        if (!in_array($minutesBefore, CalendarReminder::MINUTES_BEFORE_OPTIONS, true)) {
            throw new BadRequestHttpException("Reminders can not be sent $minutesBefore minutes before an event.");
        }

        $user = $this->requireUser();
        if ($this->getReminderState($minutesBefore) !== self::OFF) {
            $criteria = ['user' => $user, 'minutesBefore' => $minutesBefore];
            if ($this->calendar !== null) {
                $criteria['calendar'] = $this->calendar;
            }
            $this->reminderRepository->removeAll($this->reminderRepository->findBy($criteria));
        } else {
            $this->denyAccessUnlessCanView();
            $this->reminderRepository->saveAll(array_map(
                fn (Calendar $calendar) => new CalendarReminder($user, $calendar, $minutesBefore),
                $this->getCalendars(),
            ));
        }

        $this->reminderCalendarIds = null;
    }

    /**
     * @param list<int> $enabledCalendarIds
     * @return self::OFF|self::MIXED|self::ON
     */
    private function getState(array $enabledCalendarIds): string
    {
        $calendarIds = $this->getCalendarIds();
        $enabled = count(array_intersect($calendarIds, $enabledCalendarIds));

        return match (true) {
            $enabled === 0 => self::OFF,
            $enabled === count($calendarIds) => self::ON,
            default => self::MIXED,
        };
    }

    /**
     * @return list<Calendar>
     */
    private function getCalendars(): array
    {
        if ($this->calendar !== null) {
            return [$this->calendar];
        }
        return array_values($this->calendarRepository->findAllVisibleCalendars());
    }

    /**
     * @return list<int>
     */
    private function getCalendarIds(): array
    {
        return $this->calendarIds ??= array_map(fn (Calendar $calendar) => $calendar->getId(), $this->getCalendars());
    }

    /**
     * @return list<int>
     */
    private function getSubscribedCalendarIds(): array
    {
        $user = $this->getUser();
        if ($user === null || empty($this->getCalendarIds())) {
            return [];
        }

        return $this->subscribedCalendarIds ??= array_map(
            fn (Subscription $subscription) => $subscription->getSubjectId(),
            $this->subscriptionRepository->findBy([
                'user' => $user,
                'type' => EventCreatedNotificationType::TYPE,
                'subjectId' => $this->getCalendarIds(),
            ]),
        );
    }

    /**
     * @return array<int, list<int>> calendar ids, keyed by minutes before
     */
    private function getReminderCalendarIds(): array
    {
        $user = $this->getUser();
        if ($user === null || empty($this->getCalendarIds())) {
            return [];
        }

        if ($this->reminderCalendarIds === null) {
            $reminders = $this->reminderRepository->findBy([
                'user' => $user,
                'calendar' => $this->getCalendarIds(),
            ]);

            $this->reminderCalendarIds = [];
            foreach ($reminders as $reminder) {
                $this->reminderCalendarIds[$reminder->getMinutesBefore()][] = $reminder->getCalendar()->getId();
            }
        }
        return $this->reminderCalendarIds;
    }

    private function denyAccessUnlessCanView(): void
    {
        // all calendars are already limited to the ones the user can view
        if ($this->calendar === null) {
            return;
        }

        $canView = $this->security->isGranted(VoterAttribute::ACL->value, [
            'entity' => $this->calendar,
            'permission' => 'view',
        ]);
        if (!$canView) {
            throw new AccessDeniedException();
        }
    }

    private function getUser(): ?User
    {
        $user = $this->security->getUser();
        return $user instanceof User ? $user : null;
    }

    private function requireUser(): User
    {
        return $this->getUser() ?? throw new AccessDeniedException();
    }
}
