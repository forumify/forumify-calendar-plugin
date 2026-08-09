<?php

declare(strict_types=1);

namespace PluginTests\Tests\Factories;

use Forumify\Calendar\Entity\Calendar;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Calendar>
 */
class CalendarFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Calendar::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'title' => self::faker()->unique()->sentence(3),
            'color' => self::faker()->hexColor(),
        ];
    }
}
