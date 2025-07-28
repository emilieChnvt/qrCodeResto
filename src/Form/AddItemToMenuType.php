<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddItemToMenuType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('menuItems', ChoiceType::class, [
                'choices' => $options['choices'],
                'data' => $options['selected'], // 👈 ça marche pour créer ou éditer
                'multiple' => true,
                'expanded' => true,
                'label' => false,
                'mapped' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'choices' => [],
            'selected' => [],
        ]);

        $resolver->setAllowedTypes('choices', 'array');
        $resolver->setAllowedTypes('selected', 'array');
    }
}
