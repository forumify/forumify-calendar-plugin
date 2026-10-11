<?php

declare(strict_types=1);

namespace PluginTests\Tests\Traits;

use Doctrine\Common\Collections\ArrayCollection;
use Forumify\Calendar\Entity\Calendar;
use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Forumify\Testing\Factories\Core\RoleFactory;
use Forumify\Testing\Factories\Core\UserFactory;
use PluginTests\Tests\Factories\ACLFactory;

trait CalendarAccessTrait
{
    /**
     * ACLs are unique per calendar and permission, so users that need the same access should share a role.
     */
    private function createRoleThatCanView(Calendar ...$calendars): Role
    {
        $role = RoleFactory::createOne();
        foreach ($calendars as $calendar) {
            ACLFactory::createOne([
                'entity' => Calendar::class,
                'entityId' => (string)$calendar->getId(),
                'permission' => 'view',
                'roles' => [$role],
            ]);
        }

        return $role;
    }

    private function createUserWithRole(Role $role): User
    {
        return UserFactory::createOne(['roleEntities' => new ArrayCollection([$role])]);
    }
}
