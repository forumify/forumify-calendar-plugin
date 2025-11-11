<?php

declare(strict_types=1);

namespace Forumify\Calendar;

use Forumify\Plugin\AbstractForumifyPlugin;
use Forumify\Plugin\PluginMetadata;

class ForumifyCalendarPlugin extends AbstractForumifyPlugin
{
    public function getPluginMetadata(): PluginMetadata
    {
        return new PluginMetadata(
            'Calendar',
            'forumify',
            'Keep your community informed about your events using this interactive calendar.',
            'https://forumify.net',
        );
    }

    public function getPermissions(): array
    {
        return [
            'admin' => [
                'calendars' => [
                    'view',
                    'manage',
                ],
            ],
        ];
    }
}
