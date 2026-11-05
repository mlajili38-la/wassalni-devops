<?php

namespace App\Form;

use App\Entity\Reclamation;
use App\Entity\Trajet;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ReclamationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('sujet', ChoiceType::class, [
                'label' => 'Sujet de la réclamation',
                'choices' => [
                    'Problème avec un conducteur' => 'Problème avec un conducteur',
                    'Problème avec un passager' => 'Problème avec un passager',
                    'Problème de paiement' => 'Problème de paiement',
                    'Annulation de dernière minute' => 'Annulation de dernière minute',
                    'Véhicule non conforme' => 'Véhicule non conforme',
                    'Retard important' => 'Retard important',
                    'Problème avec une réservation' => 'Problème avec une réservation',
                    'Autre problème' => 'Autre problème',
                ],
                'attr' => ['class' => 'form-control'],
                'constraints' => [new NotBlank(['message' => 'Veuillez sélectionner un sujet'])],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Description détaillée',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'Décrivez votre problème en détail...'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le message est obligatoire']),
                    new Length(['min' => 10, 'minMessage' => 'Le message doit contenir au moins 10 caractères'])
                ],
            ])
            ->add('trajetConcerne', EntityType::class, [
                'label' => 'Trajet concerné',
                'class' => Trajet::class,
                'choices' => $options['trajets_disponibles'] ?? [],
                'choice_label' => function(Trajet $trajet) {
                    return sprintf(
                        '%s → %s | %s | Conducteur: %s %s | Prix: %s DT',
                        $trajet->getVilleDepart(),
                        $trajet->getDestination(),
                        $trajet->getDateDepart()->format('d/m/Y H:i'),
                        $trajet->getConducteur()->getPrenom(),
                        $trajet->getConducteur()->getNom(),
                        $trajet->getPrix()
                    );
                },
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'style' => 'width: 100%;'
                ],
                'placeholder' => '-- Sélectionnez un trajet --',
            ])
            ->add('utilisateurConcerne', EntityType::class, [
                'label' => 'Conducteur concerné (optionnel)',
                'class' => User::class,
                'choices' => $options['conducteurs_disponibles'] ?? [],
                'choice_label' => function(User $user) {
                    return $user->getPrenom() . ' ' . $user->getNom() . ' (' . $user->getEmail() . ')';
                },
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'placeholder' => '-- Sélectionnez un conducteur --',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reclamation::class,
            'trajets_disponibles' => [],
            'conducteurs_disponibles' => [],
        ]);
    }
}