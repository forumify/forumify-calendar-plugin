<?php

declare(strict_types=1);

namespace Forumify\Calendar\Component;

use DateInterval;
use DateTime;
use DateTimeZone;
use Forumify\Calendar\Entity\Calendar;
use Forumify\Calendar\Entity\CalendarEvent;
use Forumify\Calendar\Repository\CalendarEventRepository;
use Forumify\Calendar\Repository\CalendarRepository;
use Forumify\Core\Entity\User;
use Forumify\Core\Service\ACLService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('Forumify\\Calendar', '@ForumifyCalendarPlugin/frontend/components/calendar/calendar.html.twig')]
class CalendarComponent
{
    use DefaultActionTrait;

    public const float HOUR_HEIGHT = 40;
    public const float MINUTE_HEIGHT = 1 / 60 * self::HOUR_HEIGHT;

    #[LiveProp]
    public DateTime $now;

    #[LiveProp]
    public DateTime $view;

    #[LiveProp]
    public ?Calendar $selectedCalendar = null;

    #[LiveProp]
    public string $viewMode = 'month';

    /** @var array<string, list<CalendarEvent>>|null */
    private ?array $eventMemo = null;

    public function __construct(
        private readonly Security $security,
        private readonly CalendarRepository $calendarRepository,
        private readonly CalendarEventRepository $calendarEventRepository,
    ) {
    }

    public function mount(): void
    {
        $this->reset();
    }

    /**
     * @return array<Calendar>
     */
    public function getVisibleCalendars(): array
    {
        return $this->calendarRepository->findAllVisibleCalendars();
    }

    public function showManageCalendarBtn(): bool
    {
        return $this->calendarRepository->countManageableCalendars() > 0;
    }

    private function reset(): void
    {
        $this->now = new DateTime();
        $this->setTimezone($this->now);

        $this->view = clone $this->now;
    }

    public function getStartDay(): DateTime
    {
        $firstDay = clone $this->view;
        $this->setTimezone($firstDay);

        if ($this->viewMode === 'month') {
            $firstDay->modify('first day of this month');
            $offset = (int)$firstDay->format('N') - 1;
            $firstDay->sub(new DateInterval("P{$offset}D"));
        } else {
            $firstDay->modify('this week');
        }

        return $firstDay;
    }

    /**
     * @return array<CalendarEvent>
     */
    public function getEvents(DateTime $date): array
    {
        $events = $this->getAllEvents($this->view, $this->selectedCalendar);
        return $events[$date->format('Y-m-d')] ?? [];
    }

    /**
     * @return list<array{ event: CalendarEvent, height: int, top: int }>
     */
    public function getEventsWeekView(DateTime $date): array
    {
        $events = [];
        foreach ($this->getEvents($date) as $event) {
            $top = $event->getStart()->format('G') * 60 + (int)$event->getStart()->format('i');
            $top = (int)floor($top * self::MINUTE_HEIGHT);

            $heightDiff = $event->getEnd() !== null
                ? $event->getEnd()->diff($event->getStart(), true)
                : new DateInterval('PT1H');
            $height = (int)floor(($heightDiff->h * 60 + $heightDiff->i) * self::MINUTE_HEIGHT);

            $events[] = [
                'event' => $event,
                'height' => $height,
                'top' => $top,
            ];
        }
        return $events;
    }

    public function toTime(int $i): string
    {
        $hours = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        return "$hours:00";
    }

    #[LiveAction]
    public function prev(): void
    {
        if ($this->viewMode === 'month') {
            $this->view->modify('first day of previous month');
        } else {
            $this->view->modify('last week');
        }

        $this->setTimezone($this->view);
    }

    #[LiveAction]
    public function next(): void
    {
        if ($this->viewMode === 'month') {
            $this->view->modify('first day of next month');
        } else {
            $this->view->modify('next week');
        }
        $this->setTimezone($this->view);
    }

    #[LiveAction]
    public function toggleViewMode(): void
    {
        $this->viewMode = $this->viewMode === 'month' ? 'week' : 'month';
        $this->reset();
    }

    private function setTimezone(DateTime $date): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }
        $date->setTimezone(new DateTimeZone($user->getTimezone()));
    }

    /**
     * @return array<string, array<CalendarEvent>>
     */
    public function getAllEvents(\DateTime $date, ?Calendar $calendar): array
    {
        if ($this->eventMemo !== null) {
            return $this->eventMemo;
        }

        $allEvents = $this->calendarEventRepository->findByDateAndCalendar($date, $calendar);
        $tz = $date->getTimezone();
        foreach ($allEvents as $event) {
            $event->getStart()->setTimezone($tz);
            $event->getEnd()?->setTimezone($tz);
        }

        $start = clone $date;
        $start->modify('first day of previous month');

        $end = clone $date;
        $end->modify('last day of next month');

        $this->eventMemo = [];
        foreach ($allEvents as $event) {
            $this->insertEventsWithRecurrence($event, $start, $end);
        }

        assert($this->eventMemo !== null);
        foreach ($this->eventMemo as &$dayEvents) {
            uasort($dayEvents, fn (
                CalendarEvent $a,
                CalendarEvent $b,
            ) => $a->getStart()->getTimestamp() <=> $b->getStart()->getTimestamp());
        }

        return $this->eventMemo;
    }

    private function insertEventsWithRecurrence(
        CalendarEvent $event,
        DateTime $start,
        DateTime $end,
    ): void {
        $eventStart = clone $event->getStart();
        if ($event->getRepeat() === null) {
            $this->eventMemo[$eventStart->format('Y-m-d')][] = $event;
            return;
        }

        $repeatEnd = $event->getRepeatEnd();
        if ($repeatEnd !== null) {
            $repeatEnd->setTimezone($start->getTimezone());
            $repeatEnd->setTime(23, 59, 59);

            $end = $repeatEnd;
        }

        $eventEnd = $event->getEnd();
        $eventDuration = $eventEnd === null
            ? null
            : $eventStart->diff($eventEnd, true);

        while ($eventStart < $end) {
            if ($eventStart > $start) {
                $event = clone $event;
                $event->setStart(clone $eventStart);
                if ($eventDuration) {
                    $event->setEnd((clone $eventStart)->add($eventDuration));
                }

                $this->eventMemo[$eventStart->format('Y-m-d')][] = $event;
            }

            switch ($event->getRepeat()) {
                case 'daily':
                    $eventStart->add(new DateInterval('P1D'));
                    break;
                case 'weekly':
                    $eventStart->add(new DateInterval('P1W'));
                    break;
                case 'monthly':
                    $eventStart->add(new DateInterval('P1M'));
                    break;
                case 'yearly':
                    $eventStart->add(new DateInterval('P1Y'));
                    break;
                default:
                    break 2;
            }
        }
    }
}
