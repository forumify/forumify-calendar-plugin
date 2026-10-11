<?php

declare(strict_types=1);

namespace Forumify\Calendar\Repository;

use Forumify\Calendar\Entity\Calendar;
use Forumify\Calendar\Entity\CalendarEvent;
use Forumify\Core\Repository\AbstractRepository;

/**
 * @extends AbstractRepository<CalendarEvent>
 */
class CalendarEventRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return CalendarEvent::class;
    }

    /**
     * @return array<CalendarEvent>
     */
    public function findByDateAndCalendar(\DateTime $date, ?Calendar $calendar): array
    {
        $start = clone $date;
        $start->modify('first day of previous month');

        $end = clone $date;
        $end->modify('last day of next month');

        $qb = $this->createQueryBuilder('ce')->join('ce.calendar', 'c');
        $this->addACLToQuery($qb, 'view', Calendar::class, 'c');

        $qb
            ->andWhere($qb->expr()->orX(
                'ce.start BETWEEN :start AND :end',
                $qb->expr()->andX(
                    'ce.repeat IS NOT NULL',
                    $qb->expr()->orX(
                        'ce.repeatEnd IS NULL',
                        'ce.repeatEnd > :start',
                    ),
                ),
            ))
            ->orderBy('ce.start', 'ASC')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
        ;

        if ($calendar !== null) {
            $qb
                ->andWhere('ce.calendar = :calendar')
                ->setParameter('calendar', $calendar)
            ;
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Finds events in the given calendars that start in ($after, $until], or that repeat and might have an
     * occurrence in that range. Use EventRecurrence to find the exact occurrences of repeating events.
     *
     * @param list<int> $calendarIds
     * @return array<CalendarEvent>
     */
    public function findPossiblyStartingBetween(array $calendarIds, \DateTime $after, \DateTime $until): array
    {
        if (empty($calendarIds)) {
            return [];
        }

        $qb = $this->createQueryBuilder('ce');
        return $qb
            ->where('ce.calendar IN (:calendars)')
            ->andWhere($qb->expr()->orX(
                $qb->expr()->andX('ce.repeat IS NULL', 'ce.start > :after', 'ce.start <= :until'),
                $qb->expr()->andX(
                    'ce.repeat IS NOT NULL',
                    'ce.start <= :until',
                    // the repeat end is a date in the event's timezone, leave a day of slack for EventRecurrence
                    $qb->expr()->orX('ce.repeatEnd IS NULL', 'ce.repeatEnd >= :repeatEndAfter'),
                ),
            ))
            ->setParameter('calendars', $calendarIds)
            ->setParameter('after', $after)
            ->setParameter('until', $until)
            ->setParameter('repeatEndAfter', (clone $after)->modify('-1 day'))
            ->getQuery()
            ->getResult();
    }
}
