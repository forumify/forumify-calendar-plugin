<?php

declare(strict_types=1);

namespace Forumify\Calendar\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Single row that remembers up to when reminders have been sent.
 *
 * The reminder task locks it for the duration of its transaction, which keeps concurrent runs from sending
 * the same reminders twice, and commits the new position together with the notifications it created.
 */
#[ORM\Entity]
class CalendarReminderCursor
{
    public const int ID = 1;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id = self::ID;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $processedUntil;

    public function __construct(DateTimeImmutable $processedUntil)
    {
        $this->processedUntil = $processedUntil;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getProcessedUntil(): DateTimeImmutable
    {
        return $this->processedUntil;
    }

    public function setProcessedUntil(DateTimeImmutable $processedUntil): void
    {
        $this->processedUntil = $processedUntil;
    }
}
