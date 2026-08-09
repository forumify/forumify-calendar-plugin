<?php

declare(strict_types=1);

namespace Forumify\Calendar\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Calendar\Repository\CalendarRepository;
use Forumify\Core\Entity\AccessControlledEntityInterface;
use Forumify\Core\Entity\ACLParameters;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\SluggableEntityTrait;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CalendarRepository::class)]
#[ApiResource(
    routePrefix: '/calendar',
    operations: [
        new Get(
            extraProperties: ['acl' => 'view'],
        ),
        new GetCollection(
            extraProperties: ['acl' => 'view'],
        ),
        new Post(security: 'is_granted("calendar.admin.calendars.manage")'),
        new Patch(security: 'is_granted("calendar.admin.calendars.manage")'),
        new Delete(security: 'is_granted("calendar.admin.calendars.manage")'),
    ],
)]
class Calendar implements AccessControlledEntityInterface
{
    use IdentifiableEntityTrait;
    use SluggableEntityTrait;

    #[ORM\Column(length: 255)]
    #[Groups('Calendar')]
    private string $title;

    #[ORM\Column(length: 7, options: ['fixed' => true])]
    #[Groups('Calendar')]
    private string $color;

    /**
     * @var Collection<int, CalendarEvent>
     */
    #[ORM\OneToMany(mappedBy: 'calendar', targetEntity: CalendarEvent::class, cascade: ['persist', 'remove'])]
    private Collection $events;

    public function __construct()
    {
        $this->events = new ArrayCollection();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): void
    {
        $this->color = $color;
    }

    /**
     * @return Collection<int, CalendarEvent>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    /**
     * @param Collection<int, CalendarEvent> $events
     * @return void
     */
    public function setEvents(Collection $events): void
    {
        $this->events = $events;
    }

    public function getACLPermissions(): array
    {
        return ['view', 'manage_events'];
    }

    public function getACLParameters(): ACLParameters
    {
        return new ACLParameters(
            self::class,
            (string)$this->getId(),
            'forumify_admin_calendars_list',
        );
    }
}
