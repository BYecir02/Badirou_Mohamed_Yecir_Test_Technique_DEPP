<?php

namespace App\Entity;

use App\Repository\InfosRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InfosRepository::class)]
class Infos
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $rank = null;

    #[ORM\Column(length: 255)]
    private ?string $victoire = null;

    #[ORM\Column(length: 255)]
    private ?string $defaite = null;

    #[ORM\OneToOne(inversedBy: 'infos')]
    #[ORM\JoinColumn(
        name: 'user_id',
        referencedColumnName: 'id',
        nullable: false,
        unique: true
    )]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRank(): ?string
    {
        return $this->rank;
    }

    public function setRank(string $rank): static
    {
        $this->rank = $rank;

        return $this;
    }

    public function getVictoire(): ?string
    {
        return $this->victoire;
    }

    public function setVictoire(string $victoire): static
    {
        $this->victoire = $victoire;

        return $this;
    }

    public function getDefaite(): ?string
    {
        return $this->defaite;
    }

    public function setDefaite(string $defaite): static
    {
        $this->defaite = $defaite;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
