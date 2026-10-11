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

/**
 * Base for notifications about a calendar event, which is stored as "event" in the notification context.
 */
abstract class AbstractEventNotificationType extends AbstractEmailNotificationType
{
    public function __construct(
        protected readonly TranslatorInterface $translator,
        private readonly Packages $packages,
        private readonly SettingRepository $settingRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
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

    protected function getEvent(Notification $notification): ?CalendarEvent
    {
        $event = $notification->getDeserializedContext()['event'] ?? null;
        return $event instanceof CalendarEvent ? $event : null;
    }
}
