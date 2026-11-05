<?php

namespace App\Entity;

use App\Repository\VehiculeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VehiculeRepository::class)]
class Vehicule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: "La marque est obligatoire")]
    #[Assert\Length(min: 2, max: 50)]
    private ?string $marque = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: "Le modèle est obligatoire")]
    #[Assert\Length(min: 2, max: 50)]
    private ?string $modele = null;

    #[ORM\Column(length: 10)]
    #[Assert\NotBlank(message: "L'immatriculation est obligatoire")]
    #[Assert\Regex(pattern: "/^[A-Z0-9]{1,10}$/", message: "Immatriculation invalide")]
    private ?string $immatriculation = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "Le nombre de places est obligatoire")]
    #[Assert\Range(min: 1, max: 9, message: "Le nombre de places doit être entre 1 et 9")]
    private ?int $nbPlaces = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $couleur = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateAjout = null;

    #[ORM\ManyToOne(inversedBy: 'vehicules')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $proprietaire = null;

    #[ORM\OneToMany(mappedBy: 'vehicule', targetEntity: Trajet::class)]
    private \Doctrine\Common\Collections\Collection $trajets;

    public function __construct()
    {
        $this->dateAjout = new \DateTime();
        $this->trajets = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMarque(): ?string
    {
        return $this->marque;
    }

    public function setMarque(string $marque): static
    {
        $this->marque = $marque;
        return $this;
    }

    public function getModele(): ?string
    {
        return $this->modele;
    }

    public function setModele(string $modele): static
    {
        $this->modele = $modele;
        return $this;
    }

    public function getImmatriculation(): ?string
    {
        return $this->immatriculation;
    }

    public function setImmatriculation(string $immatriculation): static
    {
        $this->immatriculation = $immatriculation;
        return $this;
    }

    public function getNbPlaces(): ?int
    {
        return $this->nbPlaces;
    }

    public function setNbPlaces(int $nbPlaces): static
    {
        $this->nbPlaces = $nbPlaces;
        return $this;
    }

    public function getCouleur(): ?string
    {
        return $this->couleur;
    }

    public function setCouleur(?string $couleur): static
    {
        $this->couleur = $couleur;
        return $this;
    }

    public function getDateAjout(): ?\DateTimeInterface
    {
        return $this->dateAjout;
    }

    public function setDateAjout(\DateTimeInterface $dateAjout): static
    {
        $this->dateAjout = $dateAjout;
        return $this;
    }

    public function getProprietaire(): ?User
    {
        return $this->proprietaire;
    }

    public function setProprietaire(?User $proprietaire): static
    {
        $this->proprietaire = $proprietaire;
        return $this;
    }

    /**
     * @return \Doctrine\Common\Collections\Collection<int, Trajet>
     */
    public function getTrajets(): \Doctrine\Common\Collections\Collection
    {
        return $this->trajets;
    }

    public function addTrajet(Trajet $trajet): static
    {
        if (!$this->trajets->contains($trajet)) {
            $this->trajets->add($trajet);
            $trajet->setVehicule($this);
        }
        return $this;
    }

    public function removeTrajet(Trajet $trajet): static
    {
        if ($this->trajets->removeElement($trajet)) {
            if ($trajet->getVehicule() === $this) {
                $trajet->setVehicule(null);
            }
        }
        return $this;
    }

    public function getFullName(): string
    {
        return $this->marque . ' ' . $this->modele . ' (' . $this->immatriculation . ')';
    }
}