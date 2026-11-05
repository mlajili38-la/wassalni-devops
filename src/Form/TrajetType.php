<?php

namespace App\Form;

use App\Entity\Trajet;
use App\Entity\Vehicule;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Form\Extension\Core\Type\TextType as DateTextType;

class TrajetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('villeDepart', TextType::class, [
                'label' => 'Ville de départ',
                'constraints' => [new NotBlank(['message' => 'La ville de départ est obligatoire'])],
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Tunis, Sfax, Sousse...']
            ])
            ->add('destination', TextType::class, [
                'label' => 'Destination',
                'constraints' => [new NotBlank(['message' => 'La destination est obligatoire'])],
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Ariana, Monastir, Nabeul...']
            ])
            ->add('dateDepart', DateTextType::class, [
                'label' => 'Date de départ',
                'required' => true,
                'attr' => [
                    'class' => 'form-control datepicker',
                    'id' => 'dateDepart',
                    'placeholder' => 'jj/mm/aaaa (ex: 15/05/2026)'
                ]
            ])
            ->add('dateArrivee', DateTextType::class, [
                'label' => "Date d'arrivée",
                'required' => true,
                'attr' => [
                    'class' => 'form-control datepicker',
                    'id' => 'dateArrivee',
                    'placeholder' => 'jj/mm/aaaa (ex: 15/05/2026)'
                ]
            ])
            ->add('prix', NumberType::class, [
                'label' => 'Prix par personne (DT)',
                'constraints' => [
                    new NotBlank(['message' => 'Le prix est obligatoire']),
                    new Positive(['message' => 'Le prix doit être positif'])
                ],
                'attr' => ['class' => 'form-control', 'step' => '0.5', 'min' => '0']
            ])
            ->add('vehicule', EntityType::class, [
                'label' => 'Véhicule',
                'class' => Vehicule::class,
                'choice_label' => 'fullName',
                'constraints' => [new NotBlank(['message' => 'Veuillez sélectionner un véhicule'])],
                'attr' => ['class' => 'form-control']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (optionnel)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3, 'placeholder' => 'Informations supplémentaires...']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Trajet::class,
        ]);
    }
}