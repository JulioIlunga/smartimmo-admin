<?php

namespace App\Entity;

use App\Repository\SubscriptionsRepository;
use App\Traits\TimeStampTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Subscriptions
{
    use TimeStampTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $code = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'subscriptions')]
    private Collection $agent;

    #[ORM\Column(length: 100)]
    private ?string $status = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $currentPeriodStart = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $currentPeriodEnd = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $cancelAtPeriodEnd = null;

    #[ORM\ManyToOne(inversedBy: 'subscriptions')]
    private ?MembershipPlans $plan = null;

    /**
     * @var Collection<int, PaymentForMembership>
     */
    #[ORM\OneToMany(targetEntity: PaymentForMembership::class, mappedBy: 'Subscription')]
    private Collection $paymentForMemberships;

    #[ORM\Column(nullable: true)]
    private ?int $claimLimit = null;

    #[ORM\Column(nullable: true)]
    private ?int $claimsUsed = null;

    public function __construct()
    {
        $this->agent = new ArrayCollection();
        $this->paymentForMemberships = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
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
            $agent->setSubscriptions($this);
        }

        return $this;
    }

    public function removeAgent(User $agent): static
    {
        if ($this->agent->removeElement($agent)) {
            // set the owning side to null (unless already changed)
            if ($agent->getSubscriptions() === $this) {
                $agent->setSubscriptions(null);
            }
        }

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

    public function getCurrentPeriodStart(): ?\DateTimeInterface
    {
        return $this->currentPeriodStart;
    }

    public function setCurrentPeriodStart(\DateTimeInterface $currentPeriodStart): static
    {
        $this->currentPeriodStart = $currentPeriodStart;

        return $this;
    }

    public function getCurrentPeriodEnd(): ?\DateTimeInterface
    {
        return $this->currentPeriodEnd;
    }

    public function setCurrentPeriodEnd(\DateTimeInterface $currentPeriodEnd): static
    {
        $this->currentPeriodEnd = $currentPeriodEnd;

        return $this;
    }

    public function getCancelAtPeriodEnd(): ?\DateTimeInterface
    {
        return $this->cancelAtPeriodEnd;
    }

    public function setCancelAtPeriodEnd(\DateTimeInterface $cancelAtPeriodEnd): static
    {
        $this->cancelAtPeriodEnd = $cancelAtPeriodEnd;

        return $this;
    }

    public function getPlan(): ?MembershipPlans
    {
        return $this->plan;
    }

    public function setPlan(?MembershipPlans $plan): static
    {
        $this->plan = $plan;

        return $this;
    }

    /**
     * @return Collection<int, PaymentForMembership>
     */
    public function getPaymentForMemberships(): Collection
    {
        return $this->paymentForMemberships;
    }

    public function addPaymentForMembership(PaymentForMembership $paymentForMembership): static
    {
        if (!$this->paymentForMemberships->contains($paymentForMembership)) {
            $this->paymentForMemberships->add($paymentForMembership);
            $paymentForMembership->setSubscription($this);
        }

        return $this;
    }

    public function removePaymentForMembership(PaymentForMembership $paymentForMembership): static
    {
        if ($this->paymentForMemberships->removeElement($paymentForMembership)) {
            // set the owning side to null (unless already changed)
            if ($paymentForMembership->getSubscription() === $this) {
                $paymentForMembership->setSubscription(null);
            }
        }

        return $this;
    }

    public function getClaimLimit(): ?int
    {
        return $this->claimLimit;
    }

    public function setClaimLimit(?int $claimLimit): static
    {
        $this->claimLimit = $claimLimit;

        return $this;
    }

    public function getClaimsUsed(): ?int
    {
        return $this->claimsUsed;
    }

    public function setClaimsUsed(?int $claimsUsed): static
    {
        $this->claimsUsed = $claimsUsed;

        return $this;
    }
}
