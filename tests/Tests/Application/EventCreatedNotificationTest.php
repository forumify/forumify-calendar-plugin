<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use DateTime;
use DateTimeZone;
use Forumify\Calendar\Entity\Calendar;
use Forumify\Calendar\Notification\EventCreatedNotificationType;
use Forumify\Core\Entity\Notification;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\NotificationRepository;
use Forumify\Forum\Entity\Subscription;
use Forumify\Forum\Repository\SubscriptionRepository;
use Forumify\Testing\Factories\Core\RoleFactory;
use Forumify\Testing\Traits\SettingTrait;
use PluginTests\Tests\Factories\CalendarEventFactory;
use PluginTests\Tests\Factories\CalendarFactory;
use PluginTests\Tests\Traits\CalendarAccessTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Zenstruck\Foundry\Test\Factories;

class EventCreatedNotificationTest extends KernelTestCase
{
    use Factories;
    use SettingTrait;
    use CalendarAccessTrait;

    public function testSubscribersAreNotifiedOfNewEvents(): void
    {
        $calendar = CalendarFactory::createOne();
        $role = $this->createRoleThatCanView($calendar);
        $subscriber = $this->createUserWithRole($role);
        $bystander = $this->createUserWithRole($role);
        $this->subscribe($subscriber, $calendar);

        $event = CalendarEventFactory::createOne(['calendar' => $calendar]);

        $notifications = $this->findNotifications($subscriber);
        self::assertCount(1, $notifications);
        self::assertSame($event->getId(), $notifications[0]->getDeserializedContext()['event']->getId());
        self::assertEmpty($this->findNotifications($bystander));
    }

    public function testSubscribersOfOtherCalendarsAreNotNotified(): void
    {
        $calendar = CalendarFactory::createOne();
        $otherCalendar = CalendarFactory::createOne();
        $subscriber = $this->createUserWithRole($this->createRoleThatCanView($calendar, $otherCalendar));
        $this->subscribe($subscriber, $otherCalendar);

        CalendarEventFactory::createOne(['calendar' => $calendar]);

        self::assertEmpty($this->findNotifications($subscriber));
    }

    public function testSubscribersWithoutViewPermissionAreNotNotified(): void
    {
        $calendar = CalendarFactory::createOne();
        $subscriber = $this->createUserWithRole(RoleFactory::createOne());
        $this->subscribe($subscriber, $calendar);

        CalendarEventFactory::createOne(['calendar' => $calendar]);

        self::assertEmpty($this->findNotifications($subscriber));
    }

    public function testAuthorIsNotNotifiedOfTheirOwnEvent(): void
    {
        $calendar = CalendarFactory::createOne();
        $author = $this->createUserWithRole($this->createRoleThatCanView($calendar));
        $this->subscribe($author, $calendar);

        self::getContainer()->get('security.token_storage')->setToken(
            new UsernamePasswordToken($author, 'main', $author->getRoles()),
        );
        CalendarEventFactory::createOne(['calendar' => $calendar]);

        self::assertEmpty($this->findNotifications($author));
    }

    public function testEmailIsSentInTheRecipientsTimezone(): void
    {
        $this->setSetting('forumify.mailer.from', 'calendar@example.org');

        $calendar = CalendarFactory::createOne(['title' => 'Community Events']);
        $subscriber = $this->createUserWithRole($this->createRoleThatCanView($calendar));
        $subscriber->setTimezone('Asia/Tokyo');
        $this->subscribe($subscriber, $calendar);

        $start = new DateTime('2030-06-15 12:00:00', new DateTimeZone('UTC'));
        CalendarEventFactory::createOne([
            'calendar' => $calendar,
            'title' => 'Summer Meetup',
            'content' => '<p>Bring snacks!</p>',
            'start' => $start,
        ]);

        [$notification] = $this->findNotifications($subscriber);
        self::getContainer()->get(EventCreatedNotificationType::class)->handleNotification($notification);

        self::assertEmailCount(1);
        /** @var Email $email */
        $email = self::getMailerMessage();
        self::assertEmailAddressContains($email, 'To', $subscriber->getEmail());
        self::assertSame('A new event was posted in "Community Events"', $email->getSubject());
        self::assertEmailHtmlBodyContains($email, 'Summer Meetup');
        self::assertEmailHtmlBodyContains($email, 'Bring snacks!');
        self::assertEmailHtmlBodyContains($email, 'Saturday, June 15, 2030 9:00 PM');
        self::assertEmailHtmlBodyContains($email, 'Asia/Tokyo');
        self::assertEmailHtmlBodyContains($email, '/calendar/' . $calendar->getSlug());
    }

    private function subscribe(User $user, Calendar $calendar): void
    {
        $subscription = new Subscription();
        $subscription->setUser($user);
        $subscription->setType(EventCreatedNotificationType::TYPE);
        $subscription->setSubjectId($calendar->getId());

        self::getContainer()->get(SubscriptionRepository::class)->save($subscription);
    }

    /**
     * @return array<Notification>
     */
    private function findNotifications(User $user): array
    {
        return self::getContainer()->get(NotificationRepository::class)->findBy([
            'recipient' => $user,
            'type' => EventCreatedNotificationType::TYPE,
        ]);
    }
}
