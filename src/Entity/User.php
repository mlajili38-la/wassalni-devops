<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: "L'email est obligatoire")]
    #[Assert\Email(message: "Format email invalide")]
    private ?string $email = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le nom est obligatoire")]
    #[Assert\Length(min: 2, max: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le prénom est obligatoire")]
    #[Assert\Length(min: 2, max: 100)]
    private ?string $prenom = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: "Le téléphone est obligatoire")]
    #[Assert\Regex(pattern: "/^[0-9]{8}$/", message: "Le numéro doit contenir 8 chiffres")]
    private ?string $telephone = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    #[Assert\NotBlank(message: "Le mot de passe est obligatoire")]
    #[Assert\Length(min: 6, message: "Le mot de passe doit faire au moins 6 caractères")]
    private ?string $password = null;
    #[ORM\Column(length: 20)]
    private ?string $statut = 'ACTIF';

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateInscription = null;

    #[ORM\OneToMany(mappedBy: 'conducteur', targetEntity: Trajet::class)]
    private Collection $trajets;

    #[ORM\OneToMany(mappedBy: 'passager', targetEntity: Reservation::class)]
    private Collection $reservations;

    #[ORM\OneToMany(mappedBy: 'proprietaire', targetEntity: Vehicule::class)]
    private Collection $vehicules;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Reclamation::class)]
    private Collection $reclamations;

    public function __construct()
    {
        $this->trajets = new ArrayCollection();
        $this->reservations = new ArrayCollection();
        $this->vehicules = new ArrayCollection();
        $this->reclamations = new ArrayCollection();
        $this->dateInscription = new \DateTime();
        $this->roles = ['ROLE_PASSAGER']; 
        $this->avisDonnes = new ArrayCollection();
    $this->avisRecus = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getDateInscription(): ?\DateTimeInterface
    {
        return $this->dateInscription;
    }

    public function setDateInscription(?\DateTimeInterface $dateInscription): static
    {
        $this->dateInscription = $dateInscription;
        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * @return Collection<int, Trajet>
     */
    public function getTrajets(): Collection
    {
        return $this->trajets;
    }

    public function addTrajet(Trajet $trajet): static
    {
        if (!$this->trajets->contains($trajet)) {
            $this->trajets->add($trajet);
            $trajet->setConducteur($this);
        }
        return $this;
    }

    public function removeTrajet(Trajet $trajet): static
    {
        if ($this->trajets->removeElement($trajet)) {
            if ($trajet->getConducteur() === $this) {
                $trajet->setConducteur(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(Reservation $reservation): static
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setPassager($this);
        }
        return $this;
    }

    public function removeReservation(Reservation $reservation): static
    {
        if ($this->reservations->removeElement($reservation)) {
            if ($reservation->getPassager() === $this) {
                $reservation->setPassager(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Vehicule>
     */
    public function getVehicules(): Collection
    {
        return $this->vehicules;
    }

    public function addVehicule(Vehicule $vehicule): static
    {
        if (!$this->vehicules->contains($vehicule)) {
            $this->vehicules->add($vehicule);
            $vehicule->setProprietaire($this);
        }
        return $this;
    }

    public function removeVehicule(Vehicule $vehicule): static
    {
        if ($this->vehicules->removeElement($vehicule)) {
            if ($vehicule->getProprietaire() === $this) {
                $vehicule->setProprietaire(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Reclamation>
     */
    public function getReclamations(): Collection
    {
        return $this->reclamations;
    }

    public function addReclamation(Reclamation $reclamation): static
    {
        if (!$this->reclamations->contains($reclamation)) {
            $this->reclamations->add($reclamation);
            $reclamation->setUser($this);
        }
        return $this;
    }

    public function removeReclamation(Reclamation $reclamation): static
    {
        if ($this->reclamations->removeElement($reclamation)) {
            if ($reclamation->getUser() === $this) {
                $reclamation->setUser(null);
            }
        }
        return $this;
    }

    public function getFullName(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }
    // ========== AJOUTER CES PROPRIÉTÉS ==========
#[ORM\Column(type: 'boolean')]
private bool $isVerified = false;

#[ORM\Column(type: 'string', length: 100, nullable: true)]
private ?string $verificationToken = null;

#[ORM\Column(type: 'datetime', nullable: true)]
private ?\DateTimeInterface $verificationTokenExpiresAt = null;

// ========== AJOUTER CES GETTERS/SETTERS ==========

public function isVerified(): bool
{
    return $this->isVerified;
}

public function setIsVerified(bool $isVerified): static
{
    $this->isVerified = $isVerified;
    return $this;
}

public function getVerificationToken(): ?string
{
    return $this->verificationToken;
}

public function setVerificationToken(?string $verificationToken): static
{
    $this->verificationToken = $verificationToken;
    return $this;
}

public function getVerificationTokenExpiresAt(): ?\DateTimeInterface
{
    return $this->verificationTokenExpiresAt;
}

public function setVerificationTokenExpiresAt(?\DateTimeInterface $verificationTokenExpiresAt): static
{
    $this->verificationTokenExpiresAt = $verificationTokenExpiresAt;
    return $this;
}

public function generateVerificationToken(): void
{
    $this->verificationToken = bin2hex(random_bytes(32));
    $this->verificationTokenExpiresAt = new \DateTime('+24 hours');
}
#[ORM\OneToMany(mappedBy: 'passager', targetEntity: Avis::class)]
private Collection $avisDonnes;

#[ORM\OneToMany(mappedBy: 'conducteur', targetEntity: Avis::class)]
private Collection $avisRecus;


public function getAvisDonnes(): Collection
{
    return $this->avisDonnes;
}

public function getAvisRecus(): Collection
{
    return $this->avisRecus;
}

public function getNoteMoyenne(): float
{
    $total = 0;
    $count = count($this->avisRecus);
    if ($count === 0) return 0;
    
    foreach ($this->avisRecus as $avis) {
        $total += $avis->getNote();
    }
    return round($total / $count, 1);
}

public function getNombreAvis(): int
{
    return count($this->avisRecus);
}
}