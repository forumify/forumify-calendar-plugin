<?php

declare(strict_types=1);

namespace Forumify\Calendar\Notification;

use Forumify\Core\Entity\Notification;

class EventCreatedNotificationType extends AbstractEventNotificationType
{
    public const TYPE = 'calendar_event_created';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getTitle(Notification $notification): string
    {
        return $this->translator->trans('calendar.notification.event_created', [
            'calendar' => $this->getEvent($notification)?->getCalendar()->getTitle() ?? 'Deleted',
        ]);
    }

    public function getDescription(Notification $notification): string
    {
        return $this->getEvent($notification)?->getTitle() ?? '';
    }

    public function getEmailTemplate(Notification $notification): string
    {
        return '@ForumifyCalendarPlugin/emails/notifications/event_created.html.twig';
    }
}
