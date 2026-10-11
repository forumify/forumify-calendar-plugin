<?php

declare(strict_types=1);

namespace Forumify\Calendar\Notification;

use DateTime;
use DateTimeInterface;
use Forumify\Core\Entity\Notification;

/**
 * Context: "event", "occurrence" (start of the reminded occurrence, ATOM formatted) and "minutesBefore".
 */
class EventReminderNotificationType extends AbstractEventNotificationType
{
    public const TYPE = 'calendar_event_reminder';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getTitle(Notification $notification): string
    {
        return $this->translator->trans('calendar.notification.event_reminder', [
            'event' => $this->getEvent($notification)?->getTitle() ?? 'Deleted',
            'minutes' => $notification->getDeserializedContext()['minutesBefore'] ?? 0,
        ]);
    }

    public function getDescription(Notification $notification): string
    {
        $start = $this->getOccurrenceStart($notification);
        return $start === null
            ? ''
            : $start->setTimezone($this->getTimezone($notification))->format('l, F j, Y g:i A');
    }

    /**
     * Repeating events are reminded once per occurrence, so this can differ from the event's own start.
     */
    public function getOccurrenceStart(Notification $notification): ?DateTime
    {
        $occurrence = $notification->getDeserializedContext()['occurrence'] ?? null;
        if (!is_string($occurrence)) {
            return null;
        }

        $start = DateTime::createFromFormat(DateTimeInterface::ATOM, $occurrence);
        return $start === false ? null : $start;
    }

    public function getOccurrenceEnd(Notification $notification): ?DateTime
    {
        $event = $this->getEvent($notification);
        $start = $this->getOccurrenceStart($notification);
        $end = $event?->getEnd();
        if ($event === null || $start === null || $end === null) {
            return null;
        }

        return $start->add($event->getStart()->diff($end, true));
    }

    public function getEmailTemplate(Notification $notification): string
    {
        return '@ForumifyCalendarPlugin/emails/notifications/event_reminder.html.twig';
    }
}
