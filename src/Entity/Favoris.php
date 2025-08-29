<?php

namespace App\Entity;

use App\Repository\FavorisRepository;
use App\Traits\TimeStampTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FavorisRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Favoris
{
    use TimeStampTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'favoris')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'favoris')]
    private ?Property $property = null;

    #[ORM\Column]
    private ?bool $isSaved = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function setProperty(?Property $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function isSaved(): ?bool
    {
        return $this->isSaved;
    }

    public function setSaved(bool $isSaved): static
    {
        $this->isSaved = $isSaved;

        return $this;
    }
}
