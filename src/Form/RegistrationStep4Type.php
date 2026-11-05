<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Length;

class RegistrationStep4Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('marque', TextType::class, [
                'label' => 'Marque',
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Renault, Peugeot, BMW...'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La marque est obligatoire']),
                    new Length(['min' => 2, 'max' => 50])
                ]
            ])
            ->add('modele', TextType::class, [
                'label' => 'Modèle',
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Clio, 208, Série 3...'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le modèle est obligatoire']),
                    new Length(['min' => 2, 'max' => 50])
                ]
            ])
            ->add('immatriculation', TextType::class, [
                'label' => 'Immatriculation',
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '123 TUN 456'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'L\'immatriculation est obligatoire']),
                    new Length(['max' => 10])
                ]
            ])
            ->add('nbPlaces', NumberType::class, [
                'label' => 'Nombre de places',
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'max' => 9
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le nombre de places est obligatoire']),
                    new Range(['min' => 1, 'max' => 9, 'notInRangeMessage' => 'Le nombre de places doit être entre 1 et 9'])
                ]
            ])
            ->add('couleur', TextType::class, [
                'label' => 'Couleur',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Rouge, Bleu, Noir...'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}