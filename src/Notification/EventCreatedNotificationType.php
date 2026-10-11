<?php

declare(strict_types=1);

namespace Forumify\Calendar\Notification;

use DateTimeZone;
use Exception;
use Forumify\Calendar\Entity\CalendarEvent;
use Forumify\Core\Entity\Notification;
use Forumify\Core\Notification\AbstractEmailNotificationType;
use Forumify\Core\Repository\SettingRepository;
use Forumify\Core\Service\TimezoneResolver;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class EventCreatedNotificationType extends AbstractEmailNotificationType
{
    public const TYPE = 'calendar_event_created';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Packages $packages,
        private readonly SettingRepository $settingRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

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

    public function getImage(Notification $notification): string
    {
        $event = $this->getEvent($notification);

        $banner = $event?->getBanner();
        if ($banner !== null) {
            return $this->packages->getUrl($banner, 'forumify.asset');
        }

        $avatar = $event?->getCreatedBy()?->getAvatar() ?? $this->settingRepository->get('forumify.default_avatar');
        return empty($avatar)
            ? ''
            : $this->packages->getUrl($avatar, 'forumify.avatar');
    }

    public function getUrl(Notification $notification): string
    {
        $slug = $this->getEvent($notification)?->getCalendar()->getSlug();

        return $slug !== null
            ? $this->urlGenerator->generate('forumify_calendar_all', ['slug' => $slug])
            : '';
    }

    /**
     * Emails are sent from a worker without a logged-in user, so resolve the recipient's timezone ourselves.
     */
    public function getTimezone(Notification $notification): DateTimeZone
    {
        $default = $this->settingRepository->get(TimezoneResolver::DEFAULT_TIMEZONE_SETTING);
        $candidates = [$notification->getRecipient()->getTimezone(), is_string($default) ? $default : null];

        foreach ($candidates as $timezone) {
            if (empty($timezone)) {
                continue;
            }

            try {
                return new DateTimeZone($timezone);
            } catch (Exception) {
            }
        }

        return new DateTimeZone('UTC');
    }

    public function isSubjectValid(Notification $notification): bool
    {
        return $this->getEvent($notification) !== null;
    }

    public function getEmailTemplate(Notification $notification): string
    {
        return '@ForumifyCalendarPlugin/emails/notifications/event_created.html.twig';
    }

    private function getEvent(Notification $notification): ?CalendarEvent
    {
        $event = $notification->getDeserializedContext()['event'] ?? null;
        return $event instanceof CalendarEvent ? $event : null;
    }
}
