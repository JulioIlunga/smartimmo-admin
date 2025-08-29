<?php

namespace App\Entity;

use App\Repository\LeadClaimsRepository;
use App\Traits\TimeStampTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LeadClaimsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class LeadClaims
{
    use TimeStampTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'leadClaims')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Preference $lead = null;

    #[ORM\ManyToOne(inversedBy: 'leadClaims')]
    private ?User $agent = null;

    #[ORM\Column(length: 100)]
    private ?string $status = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $claimedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLead(): ?Preference
    {
        return $this->lead;
    }

    public function setLead(?Preference $lead): static
    {
        $this->lead = $lead;

        return $this;
    }

    public function getAgent(): ?User
    {
        return $this->agent;
    }

    public function setAgent(?User $agent): static
    {
        $this->agent = $agent;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getClaimedAt(): ?\DateTimeInterface
    {
        return $this->claimedAt;
    }

    public function setClaimedAt(\DateTimeInterface $claimedAt): static
    {
        $this->claimedAt = $claimedAt;

        return $this;
    }
}
