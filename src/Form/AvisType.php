<?php

namespace App\Form;

use App\Entity\Avis;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class AvisType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('note', ChoiceType::class, [
                'label' => 'Note',
                'choices' => [
                    '⭐ 1 étoile - Très insatisfait' => 1,
                    '⭐⭐ 2 étoiles - Insatisfait' => 2,
                    '⭐⭐⭐ 3 étoiles - Moyen' => 3,
                    '⭐⭐⭐⭐ 4 étoiles - Satisfait' => 4,
                    '⭐⭐⭐⭐⭐ 5 étoiles - Très satisfait' => 5,
                ],
                'expanded' => true,
                'multiple' => false,
                'attr' => ['class' => 'star-rating'],
                'constraints' => [new NotBlank(['message' => 'Veuillez donner une note'])],
            ])
            ->add('commentaire', TextareaType::class, [
                'label' => 'Votre commentaire (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Partagez votre expérience avec ce conducteur...'
                ],
                'constraints' => [new Length(['max' => 500, 'maxMessage' => 'Le commentaire ne peut pas dépasser 500 caractères'])],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Avis::class,
        ]);
    }
}