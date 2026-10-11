<?php

declare(strict_types=1);

namespace Forumify\Calendar\Service;

use DateInterval;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Forumify\Calendar\Entity\CalendarEvent;

class EventRecurrence
{
    private const INTERVALS = [
        'daily' => 'D',
        'weekly' => 'W',
        'monthly' => 'M',
        'annually' => 'Y',
        // not offered by the form, but accepted by the API in the past
        'yearly' => 'Y',
    ];

    /**
     * Returns the start of every occurrence of the event in the range ($after, $until].
     *
     * Occurrences are calculated in $timezone (defaults to the timezone of the event's start), so a recurring event
     * keeps the same wall-clock time across daylight saving time changes in that timezone.
     *
     * @return list<DateTime>
     */
    public function getOccurrences(
        CalendarEvent $event,
        DateTimeInterface $after,
        DateTimeInterface $until,
        ?DateTimeZone $timezone = null,
    ): array {
        $start = clone $event->getStart();
        if ($timezone !== null) {
            $start->setTimezone($timezone);
        }

        $interval = self::INTERVALS[$event->getRepeat() ?? ''] ?? null;
        if ($interval === null) {
            return $start > $after && $start <= $until ? [$start] : [];
        }

        $repeatEnd = $event->getRepeatEnd();
        if ($repeatEnd !== null) {
            // the repeat end is a date, it includes the entire day in the event's timezone
            $repeatEnd = new DateTime($repeatEnd->format('Y-m-d') . ' 23:59:59', $start->getTimezone());
            if ($repeatEnd < $until) {
                $until = $repeatEnd;
            }
        }

        $occurrences = [];
        // add multiples of the interval to the original start, adding them one at a time drifts for months
        for ($i = 0; ($occurrence = $this->nth($start, $interval, $i)) <= $until; $i++) {
            if ($occurrence > $after) {
                $occurrences[] = $occurrence;
            }
        }

        return $occurrences;
    }

    private function nth(DateTime $start, string $interval, int $n): DateTime
    {
        return (clone $start)->add(new DateInterval("P{$n}{$interval}"));
    }
}
