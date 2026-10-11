<?php

declare(strict_types=1);

namespace Forumify\Calendar\Repository;

use Forumify\Calendar\Entity\CalendarReminder;
use Forumify\Core\Repository\AbstractRepository;

/**
 * @extends AbstractRepository<CalendarReminder>
 */
class CalendarReminderRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return CalendarReminder::class;
    }

    /**
     * @return array<int, list<int>> calendar ids that have reminders, keyed by minutes before
     */
    public function findCalendarIdsByMinutesBefore(): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('DISTINCT r.minutesBefore AS minutesBefore', 'IDENTITY(r.calendar) AS calendarId')
            ->getQuery()
            ->getArrayResult();

        $calendarIds = [];
        foreach ($rows as $row) {
            $calendarIds[(int)$row['minutesBefore']][] = (int)$row['calendarId'];
        }
        return $calendarIds;
    }
}
