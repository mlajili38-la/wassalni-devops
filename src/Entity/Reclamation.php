<?php

namespace App\Entity;

use App\Repository\ReclamationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReclamationRepository::class)]
class Reclamation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le sujet est obligatoire")]
    #[Assert\Length(min: 5, max: 100, minMessage: "Le sujet doit contenir au moins 5 caractères", maxMessage: "Le sujet ne peut pas dépasser 100 caractères")]
    private ?string $sujet = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: "Le message est obligatoire")]
    #[Assert\Length(min: 10, minMessage: "Le message doit contenir au moins 10 caractères")]
    private ?string $message = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = 'EN_ATTENTE'; // EN_ATTENTE, EN_COURS, TRAITEE

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateTraitement = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reponse = null;

    #[ORM\ManyToOne(inversedBy: 'reclamations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    private ?Trajet $trajetConcerne = null;

    #[ORM\ManyToOne]
    private ?User $utilisateurConcerne = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSujet(): ?string
    {
        return $this->sujet;
    }

    public function setSujet(string $sujet): static
    {
        $this->sujet = $sujet;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        
        if ($statut === 'TRAITEE') {
            $this->dateTraitement = new \DateTime();
        }
        
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getDateTraitement(): ?\DateTimeInterface
    {
        return $this->dateTraitement;
    }

    public function setDateTraitement(?\DateTimeInterface $dateTraitement): static
    {
        $this->dateTraitement = $dateTraitement;
        return $this;
    }

    public function getReponse(): ?string
    {
        return $this->reponse;
    }

    public function setReponse(?string $reponse): static
    {
        $this->reponse = $reponse;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getTrajetConcerne(): ?Trajet
    {
        return $this->trajetConcerne;
    }

    public function setTrajetConcerne(?Trajet $trajetConcerne): static
    {
        $this->trajetConcerne = $trajetConcerne;
        return $this;
    }

    public function getUtilisateurConcerne(): ?User
    {
        return $this->utilisateurConcerne;
    }

    public function setUtilisateurConcerne(?User $utilisateurConcerne): static
    {
        $this->utilisateurConcerne = $utilisateurConcerne;
        return $this;
    }
}