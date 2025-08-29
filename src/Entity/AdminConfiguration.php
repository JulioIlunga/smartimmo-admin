<?php

namespace App\Entity;

use App\Repository\AdminConfigurationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdminConfigurationRepository::class)]
class AdminConfiguration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $preferencePrice = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lookingForAgentUrl = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPreferencePrice(): ?string
    {
        return $this->preferencePrice;
    }

    public function setPreferencePrice(?string $preferencePrice): static
    {
        $this->preferencePrice = $preferencePrice;

        return $this;
    }

    public function getLookingForAgentUrl(): ?string
    {
        return $this->lookingForAgentUrl;
    }

    public function setLookingForAgentUrl(?string $lookingForAgentUrl): static
    {
        $this->lookingForAgentUrl = $lookingForAgentUrl;

        return $this;
    }
}
