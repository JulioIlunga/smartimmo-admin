<?php

namespace App\Entity;

use App\Repository\PreferenceRepository;
use App\Traits\TimeStampTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PreferenceRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Preference
{
    use TimeStampTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $code = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $province = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $commune = null;

    #[ORM\Column(nullable: true)]
    private ?int $minPrice = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxPrice = null;

    #[ORM\Column(nullable: true)]
    private ?int $bedrooms = null;

    #[ORM\Column(nullable: true)]
    private ?int $bathrooms = null;

    #[ORM\ManyToOne(inversedBy: 'preferences')]
    private ?User $user = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $propertyType = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $transactionType = null;

    #[ORM\Column(nullable: true)]
    private ?bool $status = null;

    #[ORM\Column(nullable: true)]
    private ?bool $paid = null;

    /**
     * @var Collection<int, PaymentForPreference>
     */
    #[ORM\OneToMany(targetEntity: PaymentForPreference::class, mappedBy: 'preference')]
    private Collection $paymentForPreferences;

    /**
     * @var Collection<int, Property>
     */
    #[ORM\ManyToMany(targetEntity: Property::class, inversedBy: 'preferences')]
    private Collection $property;

    /**
     * @var Collection<int, LeadClaims>
     */
    #[ORM\OneToMany(targetEntity: LeadClaims::class, mappedBy: 'leadId')]
    private Collection $leadClaims;

    #[ORM\Column(nullable: true)]
    private ?int $claimCount = null;

    #[ORM\Column(nullable: true)]
    private ?bool $canBeClaimed = null;

    // --- Previously added lead timing fields ---
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $leadTimeframe = null; // 'urgent' | 'by_date'

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $leadUntil = null;

    // --- NEW optional fields ---

    // e.g. ["parking","wifi"]; stored as JSON
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $mustHaves = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $moveInEarliest = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $moveInLatest = null;

    // e.g. 'low' | 'medium' | 'high'
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $urgency = null;

    // store numeric days as string ("30") or a flag like "until_cancel"
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $alertDuration = null;

    // e.g. 'instant' | 'daily' | 'weekly'
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $alertFrequency = null;

    // e.g. 'whatsapp' | 'phone' | 'email'
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $contactChannel = null;

    // e.g. 'morning' | 'afternoon' | 'evening'
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $contactTime = null;

    #[ORM\Column(nullable: true)]
    private ?bool $whatsappConsent = null;

    #[ORM\Column]
    private ?bool $deleted = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    #[ORM\ManyToOne(inversedBy: 'preferences')]
    private ?Province $city = null;

    #[ORM\Column]
    private ?int $leadCost = null;

    public function __construct()
    {
        $this->paymentForPreferences = new ArrayCollection();
        $this->property = new ArrayCollection();
        $this->leadClaims = new ArrayCollection();
    }

    // ----------------- Getters / Setters -----------------

    public function getId(): ?int { return $this->id; }

    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }

    public function getCountry(): ?string { return $this->country; }
    public function setCountry(?string $country): static { $this->country = $country; return $this; }

    public function getProvince(): ?string { return $this->province; }
    public function setProvince(?string $province): static { $this->province = $province; return $this; }

    public function getCommune(): ?string { return $this->commune; }
    public function setCommune(?string $commune): static { $this->commune = $commune; return $this; }

    public function getMinPrice(): ?int { return $this->minPrice; }
    public function setMinPrice(?int $minPrice): static { $this->minPrice = $minPrice; return $this; }

    public function getMaxPrice(): ?int { return $this->maxPrice; }
    public function setMaxPrice(?int $maxPrice): static { $this->maxPrice = $maxPrice; return $this; }

    public function getBedrooms(): ?int { return $this->bedrooms; }
    public function setBedrooms(?int $bedrooms): static { $this->bedrooms = $bedrooms; return $this; }

    public function getBathrooms(): ?int { return $this->bathrooms; }
    public function setBathrooms(?int $bathrooms): static { $this->bathrooms = $bathrooms; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getPropertyType(): ?string { return $this->propertyType; }
    public function setPropertyType(?string $propertyType): static { $this->propertyType = $propertyType; return $this; }

    public function getTransactionType(): ?string { return $this->transactionType; }
    public function setTransactionType(?string $transactionType): static { $this->transactionType = $transactionType; return $this; }

    public function isStatus(): ?bool { return $this->status; }
    public function setStatus(?bool $status): static { $this->status = $status; return $this; }

    public function isPaid(): ?bool { return $this->paid; }
    public function setPaid(?bool $paid): static { $this->paid = $paid; return $this; }

    /** @return Collection<int, PaymentForPreference> */
    public function getPaymentForPreferences(): Collection { return $this->paymentForPreferences; }
    public function addPaymentForPreference(PaymentForPreference $p): static
    {
        if (!$this->paymentForPreferences->contains($p)) {
            $this->paymentForPreferences->add($p);
            $p->setPreference($this);
        }
        return $this;
    }
    public function removePaymentForPreference(PaymentForPreference $p): static
    {
        if ($this->paymentForPreferences->removeElement($p)) {
            if ($p->getPreference() === $this) {
                $p->setPreference(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, Property> */
    public function getProperty(): Collection { return $this->property; }
    public function addProperty(Property $property): static
    {
        if (!$this->property->contains($property)) {
            $this->property->add($property);
        }
        return $this;
    }
    public function removeProperty(Property $property): static
    {
        $this->property->removeElement($property);
        return $this;
    }
    /**
     * @return Collection<int, LeadClaims>
     */
    public function getLeadClaims(): Collection
    {
        return $this->leadClaims;
    }

    public function addLeadClaim(LeadClaims $leadClaim): static
    {
        if (!$this->leadClaims->contains($leadClaim)) {
            $this->leadClaims->add($leadClaim);
            $leadClaim->setLead($this);
        }

        return $this;
    }

    public function removeLeadClaim(LeadClaims $leadClaim): static
    {
        if ($this->leadClaims->removeElement($leadClaim)) {
            // set the owning side to null (unless already changed)
            if ($leadClaim->getLead() === $this) {
                $leadClaim->setLead(null);
            }
        }

        return $this;
    }

    public function getClaimCount(): ?int { return $this->claimCount; }
    public function setClaimCount(?int $claimCount): static { $this->claimCount = $claimCount; return $this; }

    public function isCanBeClaimed(): ?bool { return $this->canBeClaimed; }
    public function setCanBeClaimed(?bool $canBeClaimed): static { $this->canBeClaimed = $canBeClaimed; return $this; }

    public function getLeadTimeframe(): ?string { return $this->leadTimeframe; }
    public function setLeadTimeframe(?string $leadTimeframe): static { $this->leadTimeframe = $leadTimeframe; return $this; }

    public function getLeadUntil(): ?\DateTimeInterface { return $this->leadUntil; }
    public function setLeadUntil(?\DateTimeInterface $leadUntil): static { $this->leadUntil = $leadUntil; return $this; }

    // --- NEW fields accessors ---

    public function getMustHaves(): ?array { return $this->mustHaves; }
    public function setMustHaves(?array $mustHaves): static { $this->mustHaves = $mustHaves; return $this; }

    public function getMoveInEarliest(): ?\DateTimeInterface { return $this->moveInEarliest; }
    public function setMoveInEarliest(?\DateTimeInterface $moveInEarliest): static { $this->moveInEarliest = $moveInEarliest; return $this; }

    public function getMoveInLatest(): ?\DateTimeInterface { return $this->moveInLatest; }
    public function setMoveInLatest(?\DateTimeInterface $moveInLatest): static { $this->moveInLatest = $moveInLatest; return $this; }

    public function getUrgency(): ?string { return $this->urgency; }
    public function setUrgency(?string $urgency): static { $this->urgency = $urgency; return $this; }

    public function getAlertDuration(): ?string { return $this->alertDuration; }
    public function setAlertDuration(?string $alertDuration): static { $this->alertDuration = $alertDuration; return $this; }

    public function getAlertFrequency(): ?string { return $this->alertFrequency; }
    public function setAlertFrequency(?string $alertFrequency): static { $this->alertFrequency = $alertFrequency; return $this; }

    public function getContactChannel(): ?string { return $this->contactChannel; }
    public function setContactChannel(?string $contactChannel): static { $this->contactChannel = $contactChannel; return $this; }

    public function getContactTime(): ?string { return $this->contactTime; }
    public function setContactTime(?string $contactTime): static { $this->contactTime = $contactTime; return $this; }

    public function getWhatsappConsent(): ?bool { return $this->whatsappConsent; }
    public function setWhatsappConsent(?bool $whatsappConsent): static { $this->whatsappConsent = $whatsappConsent; return $this; }

    public function isDeleted(): ?bool
    {
        return $this->deleted;
    }

    public function setDeleted(bool $deleted): static
    {
        $this->deleted = $deleted;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeInterface $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getCity(): ?Province
    {
        return $this->city;
    }

    public function setCity(?Province $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getLeadCost(): ?int
    {
        return $this->leadCost;
    }

    public function setLeadCost(int $leadCost): static
    {
        $this->leadCost = $leadCost;

        return $this;
    }
}


//<?php
//
//namespace App\Entity;
//
//use App\Repository\PreferenceRepository;
//use App\Traits\TimeStampTrait;
//use Doctrine\Common\Collections\ArrayCollection;
//use Doctrine\Common\Collections\Collection;
//use Doctrine\ORM\Mapping as ORM;
//
//#[ORM\Entity(repositoryClass: PreferenceRepository::class)]
//#[ORM\HasLifecycleCallbacks]
//class Preference
//{
//
//    use TimeStampTrait;
//    #[ORM\Id]
//    #[ORM\GeneratedValue]
//    #[ORM\Column]
//    private ?int $id = null;
//
//    #[ORM\Column(length: 100)]
//    private ?string $code = null;
//
//    #[ORM\Column(length: 100, nullable: true)]
//    private ?string $province = null;
//
//    #[ORM\Column(length: 100, nullable: true)]
//    private ?string $commune = null;
//
//    #[ORM\Column(nullable: true)]
//    private ?int $minPrice = null;
//
//    #[ORM\Column(nullable: true)]
//    private ?int $maxPrice = null;
//
//    #[ORM\Column(nullable: true)]
//    private ?int $bedrooms = null;
//
//    #[ORM\Column(nullable: true)]
//    private ?int $bathrooms = null;
//
//    #[ORM\ManyToOne(inversedBy: 'preferences')]
//    private ?User $user = null;
//
//    #[ORM\Column(length: 50, nullable: true)]
//    private ?string $propertyType = null;
//
//    #[ORM\Column(length: 10, nullable: true)]
//    private ?string $transactionType = null;
//
//    #[ORM\Column(nullable: true)]
//    private ?bool $status = null;
//
//    #[ORM\Column(nullable: true)]
//    private ?bool $paid = null;
//
//    /**
//     * @var Collection<int, PaymentForPreference>
//     */
//    #[ORM\OneToMany(targetEntity: PaymentForPreference::class, mappedBy: 'preference')]
//    private Collection $paymentForPreferences;
//
//    /**
//     * @var Collection<int, Property>
//     */
//    #[ORM\ManyToMany(targetEntity: Property::class, inversedBy: 'preferences')]
//    private Collection $property;
//
//    /**
//     * @var Collection<int, LeadClaims>
//     */
//    #[ORM\OneToMany(targetEntity: LeadClaims::class, mappedBy: 'leadId')]
//    private Collection $leadClaims;
//
//    #[ORM\Column(nullable: true)]
//    private ?int $claimCount = null;
//
//    #[ORM\Column(nullable: true)]
//    private ?bool $canBeClaimed = null;
//
//    public function __construct()
//    {
//        $this->paymentForPreferences = new ArrayCollection();
//        $this->property = new ArrayCollection();
//        $this->leadClaims = new ArrayCollection();
//    }
//
//    public function getId(): ?int
//    {
//        return $this->id;
//    }
//
//    public function getCode(): ?string
//    {
//        return $this->code;
//    }
//
//    public function setCode(string $code): static
//    {
//        $this->code = $code;
//        return $this;
//    }
//
//    public function getProvince(): ?string
//    {
//        return $this->province;
//    }
//
//    public function setProvince(?string $province): static
//    {
//        $this->province = $province;
//
//        return $this;
//    }
//
//    public function getCommune(): ?string
//    {
//        return $this->commune;
//    }
//
//    public function setCommune(?string $commune): static
//    {
//        $this->commune = $commune;
//
//        return $this;
//    }
//
//    public function getMinPrice(): ?int
//    {
//        return $this->minPrice;
//    }
//
//    public function setMinPrice(?int $minPrice): static
//    {
//        $this->minPrice = $minPrice;
//
//        return $this;
//    }
//
//    public function getMaxPrice(): ?int
//    {
//        return $this->maxPrice;
//    }
//
//    public function setMaxPrice(?int $maxPrice): static
//    {
//        $this->maxPrice = $maxPrice;
//
//        return $this;
//    }
//
//    public function getBedrooms(): ?int
//    {
//        return $this->bedrooms;
//    }
//
//    public function setBedrooms(?int $bedrooms): static
//    {
//        $this->bedrooms = $bedrooms;
//
//        return $this;
//    }
//
//    public function getBathrooms(): ?int
//    {
//        return $this->bathrooms;
//    }
//
//    public function setBathrooms(?int $bathrooms): static
//    {
//        $this->bathrooms = $bathrooms;
//
//        return $this;
//    }
//
//    public function getUser(): ?User
//    {
//        return $this->user;
//    }
//
//    public function setUser(?User $user): static
//    {
//        $this->user = $user;
//
//        return $this;
//    }
//
//    public function getPropertyType(): ?string
//    {
//        return $this->propertyType;
//    }
//
//    public function setPropertyType(?string $propertyType): static
//    {
//        $this->propertyType = $propertyType;
//
//        return $this;
//    }
//
//    public function getTransactionType(): ?string
//    {
//        return $this->transactionType;
//    }
//
//    public function setTransactionType(string $transactionType): static
//    {
//        $this->transactionType = $transactionType;
//
//        return $this;
//    }
//
//    public function isStatus(): ?bool
//    {
//        return $this->status;
//    }
//
//    public function setStatus(?bool $status): static
//    {
//        $this->status = $status;
//
//        return $this;
//    }
//
//    public function isPaid(): ?bool
//    {
//        return $this->paid;
//    }
//
//    public function setPaid(?bool $paid): static
//    {
//        $this->paid = $paid;
//
//        return $this;
//    }
//
//    /**
//     * @return Collection<int, PaymentForPreference>
//     */
//    public function getPaymentForPreferences(): Collection
//    {
//        return $this->paymentForPreferences;
//    }
//
//    public function addPaymentForPreference(PaymentForPreference $paymentForPreference): static
//    {
//        if (!$this->paymentForPreferences->contains($paymentForPreference)) {
//            $this->paymentForPreferences->add($paymentForPreference);
//            $paymentForPreference->setPreference($this);
//        }
//
//        return $this;
//    }
//
//    public function removePaymentForPreference(PaymentForPreference $paymentForPreference): static
//    {
//        if ($this->paymentForPreferences->removeElement($paymentForPreference)) {
//            // set the owning side to null (unless already changed)
//            if ($paymentForPreference->getPreference() === $this) {
//                $paymentForPreference->setPreference(null);
//            }
//        }
//
//        return $this;
//    }
//
//    /**
//     * @return Collection<int, Property>
//     */
//    public function getProperty(): Collection
//    {
//        return $this->property;
//    }
//
//    public function addProperty(Property $property): static
//    {
//        if (!$this->property->contains($property)) {
//            $this->property->add($property);
//        }
//
//        return $this;
//    }
//
//    public function removeProperty(Property $property): static
//    {
//        $this->property->removeElement($property);
//
//        return $this;
//    }
//
//    /**
//     * @return Collection<int, LeadClaims>
//     */
//    public function getLeadClaims(): Collection
//    {
//        return $this->leadClaims;
//    }
//
//    public function addLeadClaim(LeadClaims $leadClaim): static
//    {
//        if (!$this->leadClaims->contains($leadClaim)) {
//            $this->leadClaims->add($leadClaim);
//            $leadClaim->setLeadId($this);
//        }
//
//        return $this;
//    }
//
//    public function removeLeadClaim(LeadClaims $leadClaim): static
//    {
//        if ($this->leadClaims->removeElement($leadClaim)) {
//            // set the owning side to null (unless already changed)
//            if ($leadClaim->getLeadId() === $this) {
//                $leadClaim->setLeadId(null);
//            }
//        }
//
//        return $this;
//    }
//
//    public function getClaimCount(): ?int
//    {
//        return $this->claimCount;
//    }
//
//    public function setClaimCount(?int $claimCount): static
//    {
//        $this->claimCount = $claimCount;
//
//        return $this;
//    }
//
//    public function isCanBeClaimed(): ?bool
//    {
//        return $this->canBeClaimed;
//    }
//
//    public function setCanBeClaimed(?bool $canBeClaimed): static
//    {
//        $this->canBeClaimed = $canBeClaimed;
//
//        return $this;
//    }
//}
