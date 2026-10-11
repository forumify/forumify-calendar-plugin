<?php

declare(strict_types=1);

namespace Forumify\Calendar\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Forumify\Calendar\Entity\CalendarEvent;
use Forumify\Calendar\Notification\EventCreatedNotificationType;
use Forumify\Core\Entity\Notification;
use Forumify\Core\Notification\NotificationService;
use Forumify\Core\Security\VoterAttribute;
use Forumify\Forum\Repository\SubscriptionRepository;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEntityListener(event: Events::postPersist, method: 'sendNotifications', entity: CalendarEvent::class)]
class EventCreatedNotificationListener
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly NotificationService $notificationService,
        private readonly Security $security,
    ) {
    }

    public function sendNotifications(CalendarEvent $event): void
    {
        $calendar = $event->getCalendar();
        $selfIdentifier = $this->security->getUser()?->getUserIdentifier();
        $subscriptions = $this->subscriptionRepository->findBy([
            'subjectId' => $calendar->getId(),
            'type' => EventCreatedNotificationType::TYPE,
        ]);

        $notifications = [];
        foreach ($subscriptions as $subscription) {
            $subscriber = $subscription->getUser();
            if ($subscriber->getUserIdentifier() === $selfIdentifier) {
                continue;
            }

            // subscribing doesn't check permissions, and they may have been revoked since
            $canViewCalendar = $this->security->isGrantedForUser($subscriber, VoterAttribute::ACL->value, [
                'entity' => $calendar,
                'permission' => 'view',
            ]);
            if (!$canViewCalendar) {
                continue;
            }

            $notifications[] = new Notification(
                EventCreatedNotificationType::TYPE,
                $subscriber,
                ['event' => $event],
            );
        }

        if (!empty($notifications)) {
            $this->notificationService->sendNotification($notifications);
        }
    }
}
