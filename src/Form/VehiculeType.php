<?php

namespace App\Form;

use App\Entity\Vehicule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VehiculeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('marque', TextType::class, [
                'label' => 'Marque',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Renault, Peugeot, BMW']
            ])
            ->add('modele', TextType::class, [
                'label' => 'Modèle',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Clio, 208, Série 3']
            ])
            ->add('immatriculation', TextType::class, [
                'label' => 'Immatriculation',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: 123 TUN 456']
            ])
            ->add('nbPlaces', NumberType::class, [
                'label' => 'Nombre de places',
                'attr' => ['class' => 'form-control', 'min' => 1, 'max' => 9]
            ])
            ->add('couleur', TextType::class, [
                'label' => 'Couleur',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Rouge, Bleu, Noir']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Vehicule::class,
        ]);
    }
}