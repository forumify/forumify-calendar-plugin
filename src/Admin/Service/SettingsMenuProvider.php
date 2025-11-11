<?php

declare(strict_types=1);

namespace Forumify\Calendar\Admin\Service;

use Forumify\Admin\Service\SettingsMenuProviderInterface;
use Forumify\Core\MenuBuilder\Menu;
use Forumify\Core\MenuBuilder\MenuItem;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsDecorator(SettingsMenuProviderInterface::class)]
class SettingsMenuProvider implements SettingsMenuProviderInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly SettingsMenuProviderInterface $decorated,
    ) {
    }

    public function provide(UrlGeneratorInterface $u, TranslatorInterface $t): Menu
    {
        $menu = $this->decorated->provide($u, $t);
        $menu->addItem(new MenuItem($t->trans('admin.calendar.crud.plural'), $u->generate('forumify_admin_calendars_list'), [
            'icon' => 'ph ph-calendar',
            'permission' => 'calendar.admin.calendars.view',
        ]));

        return $menu;
    }
}
