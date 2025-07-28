<?php

namespace App\Form;

use App\Entity\Menu;
use App\Entity\MenuCategory;
use App\Entity\Restaurant;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class MenuType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')

            ->add('categories', EntityType::class, [
                'class' => MenuCategory::class,
                'choices' => $options['categories'],
                'required' => true,
                'multiple' => true,
                'expanded' => true,
                'choice_label' => 'name',
                'error_bubbling' => false, // 👈 AJOUT IMPORTANT ICI

                'by_reference' => false, // <- IMPORTANT pour appeler add/removeCategory
                'constraints' => [
                    new Count([
                        'min' => 1,
                        'minMessage' => 'Vous devez sélectionner au moins une catégorie.',
                    ]),
                ],

            ])
            ->add('price', MoneyType::class, [
                'required' => false,  // <-- ici on désactive required HTML5

                'constraints' => [
                    new NotBlank([
                        'message' => 'Le prix est obligatoire.',
                    ]),
                    new PositiveOrZero([
                        'message' => 'Le prix doit être supérieur ou égal à 0.',
                    ]),
                ],
            ])        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Menu::class,
            'categories' => [], // <- ici, une nouvelle option personnalisée
            'menuItems' => [],
        ]);

    }
}
