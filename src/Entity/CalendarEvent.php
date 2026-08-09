<?php

declare(strict_types=1);

namespace Forumify\Calendar\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\CreateProvider;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Api\Entity\NewAsset;
use Forumify\Api\Serializer\Attribute\Asset;
use Forumify\Calendar\Repository\CalendarEventRepository;
use Forumify\Core\Entity\BlameableEntityTrait;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\SluggableEntityTrait;
use Forumify\Core\Entity\TimestampableEntityTrait;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CalendarEventRepository::class)]
#[ApiResource(
    routePrefix: '/calendar',
    operations: [
        new Get(
            extraProperties: ['acl' => [
                'permission' => 'view',
                'entity' => 'calendar',
            ]],
        ),
        new GetCollection(
            extraProperties: ['acl' => [
                'permission' => 'view',
                'entity' => 'calendar',
            ]],
        ),
        new Patch(
            extraProperties: ['acl' => [
                'permission' => 'manage_events',
                'entity' => 'calendar',
            ]],
        ),
        new Delete(
            extraProperties: ['acl' => [
                'permission' => 'manage_events',
                'entity' => 'calendar',
            ]],
        ),
        new GetCollection(
            uriTemplate: '/{calendarId}/events',
            uriVariables: [
                'calendarId' => new Link(fromClass: Calendar::class, toProperty: 'calendar'),
            ],
            extraProperties: ['acl' => [
                'permission' => 'view',
                'entity' => 'calendar',
            ]],
        ),
        new Post(
            uriTemplate: '/{calendarId}/events',
            uriVariables: [
                'calendarId' => new Link(
                    fromClass: Calendar::class,
                    toProperty: 'calendar',
                    security: 'is_granted("ACCESS_CONTROL_LIST", {"permission": "manage_events", "entity": calendar})',
                ),
            ],
            provider: CreateProvider::class,
        ),
    ],
)]
class CalendarEvent
{
    use IdentifiableEntityTrait;
    use SluggableEntityTrait;
    use TimestampableEntityTrait;
    use BlameableEntityTrait;

    #[ORM\Column(length: 255)]
    #[Groups('CalendarEvent')]
    private string $title;

    #[ORM\Column(type: 'datetime')]
    #[Groups('CalendarEvent')]
    private DateTime $start;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups('CalendarEvent')]
    private ?DateTime $end = null;

    #[ORM\Column('`repeat`', nullable: true)]
    #[Groups('CalendarEvent')]
    private ?string $repeat = null;

    #[ORM\Column(nullable: true)]
    #[Groups('CalendarEvent')]
    private ?DateTime $repeatEnd = null;

    #[ORM\Column(type: 'text')]
    #[Groups('CalendarEvent')]
    private string $content;

    #[ORM\Column(nullable: true)]
    #[Groups('CalendarEvent')]
    private ?string $banner = null;

    #[Asset('banner', 'forumify.asset', 'asset.storage')]
    public ?NewAsset $newBanner = null;

    #[ORM\ManyToOne(targetEntity: Calendar::class, inversedBy: 'events')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[Groups('CalendarEvent')]
    private Calendar $calendar;

    public function getStart(): DateTime
    {
        return $this->start;
    }

    public function setStart(DateTime $start): void
    {
        $this->start = $start;
    }

    public function getEnd(): ?DateTime
    {
        return $this->end;
    }

    public function setEnd(?DateTime $end): void
    {
        $this->end = $end;
    }

    public function getRepeat(): ?string
    {
        return $this->repeat;
    }

    public function setRepeat(?string $repeat): void
    {
        $this->repeat = $repeat;
    }

    public function getRepeatEnd(): ?DateTime
    {
        return $this->repeatEnd;
    }

    public function setRepeatEnd(?DateTime $repeatEnd): void
    {
        $this->repeatEnd = $repeatEnd;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getBanner(): ?string
    {
        return $this->banner;
    }

    public function setBanner(?string $banner): void
    {
        $this->banner = $banner;
    }

    public function getCalendar(): Calendar
    {
        return $this->calendar;
    }

    public function setCalendar(Calendar $calendar): void
    {
        $this->calendar = $calendar;
    }
}
