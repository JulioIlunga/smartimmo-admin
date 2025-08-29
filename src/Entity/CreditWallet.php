<?php

namespace App\Entity;

use App\Repository\CreditWalletRepository;
use App\Traits\TimeStampTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CreditWalletRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CreditWallet
{
    use TimeStampTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'creditWallet', cascade: ['all'], fetch: 'EAGER')]
    private Collection $agent;

    #[ORM\Column]
    private ?int $balanceCredits = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $lastRefillAt = null;

    public function __construct()
    {
        $this->agent = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, User>
     */
    public function getAgent(): Collection
    {
        return $this->agent;
    }

    public function addAgent(User $agent): static
    {
        if (!$this->agent->contains($agent)) {
            $this->agent->add($agent);
            $agent->setCreditWallet($this);
        }

        return $this;
    }

    public function removeAgent(User $agent): static
    {
        if ($this->agent->removeElement($agent)) {
            // set the owning side to null (unless already changed)
            if ($agent->getCreditWallet() === $this) {
                $agent->setCreditWallet(null);
            }
        }

        return $this;
    }

    public function getBalanceCredits(): ?int
    {
        return $this->balanceCredits;
    }

    public function setBalanceCredits(int $balanceCredits): static
    {
        $this->balanceCredits = $balanceCredits;

        return $this;
    }

    public function getLastRefillAt(): ?\DateTimeInterface
    {
        return $this->lastRefillAt;
    }

    public function setLastRefillAt(\DateTimeInterface $lastRefillAt): static
    {
        $this->lastRefillAt = $lastRefillAt;

        return $this;
    }
}
