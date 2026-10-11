<?php

declare(strict_types=1);

namespace Forumify\Calendar\Entity;

use Doctrine\ORM\Mapping as ORM;
use Forumify\Calendar\Repository\CalendarReminderRepository;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\User;

/**
 * A user's standing request to be reminded before every event in a calendar starts.
 */
#[ORM\Entity(repositoryClass: CalendarReminderRepository::class)]
#[ORM\UniqueConstraint(name: 'calendar_reminder_uniq', fields: ['user', 'calendar', 'minutesBefore'])]
class CalendarReminder
{
    use IdentifiableEntityTrait;

    /**
     * Kept short on purpose, the reminder task runs one query per offset that is in use.
     */
    public const array MINUTES_BEFORE_OPTIONS = [15, 60, 1440];

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Calendar::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Calendar $calendar;

    #[ORM\Column(type: 'integer')]
    private int $minutesBefore;

    public function __construct(User $user, Calendar $calendar, int $minutesBefore)
    {
        $this->user = $user;
        $this->calendar = $calendar;
        $this->minutesBefore = $minutesBefore;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCalendar(): Calendar
    {
        return $this->calendar;
    }

    public function getMinutesBefore(): int
    {
        return $this->minutesBefore;
    }
}
