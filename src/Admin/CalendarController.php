<?php

declare(strict_types=1);

namespace Forumify\Admin\Controller;

use Forumify\Admin\Crud\AbstractCrudController;
use Forumify\Admin\Form\CalendarType;
use Forumify\Calendar\Entity\Calendar;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/calendars', 'calendars')]
class CalendarController extends AbstractCrudController
{
    protected ?string $permissionView = 'calendar.admin.calendars.view';
    protected ?string $permissionCreate = 'calendar.admin.calendars.manage';
    protected ?string $permissionEdit = 'calendar.admin.calendars.manage';
    protected ?string $permissionDelete = 'calendar.admin.calendars.manage';

    protected function getEntityClass(): string
    {
        return Calendar::class;
    }

    protected function getTableName(): string
    {
        return 'Forumify\\CalendarTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(CalendarType::class, $data);
    }
}
