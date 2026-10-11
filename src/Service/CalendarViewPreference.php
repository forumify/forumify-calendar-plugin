<?php

declare(strict_types=1);

namespace Forumify\Calendar\Service;

use DateTimeImmutable;
use Forumify\Calendar\Component\CalendarComponent;
use Forumify\Core\Compliance\Disclosure\CookieCategory;
use Forumify\Core\Compliance\Disclosure\CookieDisclosure;
use Forumify\Core\Compliance\PrivacyDisclosureProviderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Remembers the calendar view mode a visitor picked last, in a cookie like the theme preference.
 * Without one, the view mode controller picks the month or list view depending on the screen width.
 */
class CalendarViewPreference implements EventSubscriberInterface, PrivacyDisclosureProviderInterface
{
    public const string COOKIE = 'forumify_calendar_view';

    private ?string $remembered = null;

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function getRememberedViewMode(): ?string
    {
        $viewMode = $this->requestStack->getCurrentRequest()?->cookies->getString(self::COOKIE);
        return in_array($viewMode, CalendarComponent::VIEW_MODES, true) ? $viewMode : null;
    }

    public function remember(string $viewMode): void
    {
        $this->remembered = $viewMode;
    }

    public function onResponse(ResponseEvent $event): void
    {
        if ($this->remembered === null || !$event->isMainRequest()) {
            return;
        }

        $event->getResponse()->headers->setCookie(new Cookie(
            self::COOKIE,
            $this->remembered,
            new DateTimeImmutable('+1 year'),
        ));
        $this->remembered = null;
    }

    public function getDisclosures(): iterable
    {
        yield new CookieDisclosure(
            self::COOKIE,
            'calendar.view_mode_cookie_purpose',
            'privacy_policy.cookie.lifetime.year_1',
            CookieCategory::Functional,
        );
    }
}
