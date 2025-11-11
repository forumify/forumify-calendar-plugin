<?php

declare(strict_types=1);

namespace Forumify\Calendar\Form;

use Forumify\Calendar\Entity\Calendar;
use Forumify\Calendar\Entity\CalendarEvent;
use Forumify\Calendar\Repository\CalendarRepository;
use Forumify\Core\Entity\User;
use Forumify\Core\Form\RichTextEditorType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<CalendarEvent>
 */
class EventType extends AbstractType
{
    public function __construct(
        private readonly CalendarRepository $calendarRepository,
        private readonly Security $security,
        private readonly Packages $packages,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CalendarEvent::class,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User|null $user */
        $user = $this->security->getUser();

        $builder
            ->add('calendar', EntityType::class, [
                'autocomplete' => true,
                'choice_label' => 'title',
                'class' => Calendar::class,
                'placeholder' => 'Select a calendar',
                'query_builder' => $this->calendarRepository->getManageableCalendarsQuery(),
            ])
            ->add('start', DateTimeType::class, [
                'view_timezone' => $user?->getTimezone() ?? 'UTC',
                'widget' => 'single_text',
            ])
            ->add('end', DateTimeType::class, [
                'required' => false,
                'view_timezone' => $user?->getTimezone() ?? 'UTC',
                'widget' => 'single_text',
            ])
            ->add('repeat', ChoiceType::class, [
                'choices' => [
                    'Annually' => 'annually',
                    'Daily' => 'daily',
                    'Monthly' => 'monthly',
                    'Weekly' => 'weekly',
                ],
                'placeholder' => 'Never',
                'required' => false,
            ])
            ->add('repeatEnd', DateType::class, [
                'help' => 'When to stop repeating the event',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('title', TextType::class)
            ->add('newBanner', FileType::class, [
                'attr' => [
                    'preview' => ($banner = $options['data']->getBanner() ?? null)
                        ? $this->packages->getUrl($banner, 'forumify.asset')
                        : null,
                ],
                'constraints' => [
                    new Assert\Image(maxSize: '2M'),
                ],
                'help' => 'Maximum 2MB, recommended is a landscape image with max width of 800px.',
                'label' => 'Banner',
                'mapped' => false,
                'required' => false,
            ])
            ->add('content', RichTextEditorType::class, [
                'empty_data' => '',
                'required' => false,
            ])
        ;
    }
}
