<?php

declare(strict_types=1);

namespace PluginTests\Tests\Factories;

use Forumify\Calendar\Entity\CalendarEvent;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<CalendarEvent>
 */
class CalendarEventFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return CalendarEvent::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'title' => self::faker()->unique()->sentence(3),
            'content' => self::faker()->paragraph(),
            'start' => self::faker()->dateTimeBetween('-1 month', '+1 month'),
            'calendar' => CalendarFactory::new(),
        ];
    }
}
