<?php

namespace App\Entity;

use App\Enum\RelationshipToPropertyEnum;
use App\Repository\PropertyRepository;
use App\Traits\TimeStampTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PropertyRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Property
{
    use TimeStampTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(length: 100)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $typeLocation = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adress = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $province = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(nullable: true)]
    private ?bool $status = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $bedroom = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $bathroom = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $price = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $currency = null;

    #[ORM\OneToMany(mappedBy: 'property', targetEntity: Images::class)]
    private Collection $images;

    #[ORM\Column(nullable: true)]
    private ?bool $publish = null;

    /**
     * @var Collection<int, Favoris>
     */
    #[ORM\OneToMany(targetEntity: Favoris::class, mappedBy: 'property')]
    private Collection $favoris;

    #[ORM\ManyToOne(inversedBy: 'properties')]
    private ?User $user = null;

    #[ORM\Column(type: Types::BINARY, nullable: true)]
    private $uuid = null;

    #[ORM\Column]
    private ?int $registrationStep = null;


    #[ORM\ManyToOne(inversedBy: 'properties')]
    private ?Province $propertyProvince = null;

    #[ORM\ManyToOne(inversedBy: 'properties')]
    private ?Country $propertyCountry = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $guests = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $livingroom = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $periodicity = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $appartementNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $uuidProperty = null;

    /**
     * @var Collection<int, Messenger>
     */
    #[ORM\OneToMany(targetEntity: Messenger::class, mappedBy: 'property')]
    private Collection $messengers;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $publishAt = null;

    #[ORM\ManyToOne(inversedBy: 'properties')]
    private ?Commune $commune = null;

    #[ORM\Column]
    private ?bool $addressView = null;

    #[ORM\Column]
    private ?bool $furniture = null;

    #[ORM\Column]
    private ?bool $airCondition = null;

    #[ORM\Column]
    private ?bool $pool = null;

    #[ORM\Column]
    private ?bool $openspaceroof = null;

    #[ORM\Column]
    private ?bool $exteriortoilet = null;

    #[ORM\Column]
    private ?bool $securityguard = null;

    #[ORM\Column]
    private ?bool $garden = null;

    #[ORM\Column]
    private ?bool $wifi = null;

    #[ORM\Column]
    private ?bool $parking = null;

    #[ORM\ManyToOne(inversedBy: 'properties')]
    private ?PropertyStatus $propertyStatus = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $unpublishAt = null;

    /**
     * @var Collection<int, PropertyReport>
     */
    #[ORM\OneToMany(targetEntity: PropertyReport::class, mappedBy: 'property')]
    private Collection $propertyReports;

    /**
     * @var Collection<int, Reservation>
     */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'property')]
    private Collection $reservations;

    /**
     * @var Collection<int, ServiceSup>
     */
    #[ORM\ManyToMany(targetEntity: ServiceSup::class, inversedBy: 'properties')]
    private Collection $serviceSup;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $header = null;

    #[ORM\Column(nullable: true)]
    private ?int $pourcerntageOfBooking = null;

    #[ORM\ManyToOne(inversedBy: 'property')]
    private ?CategoryForProperty $category = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $PriceOfVisit = null;

    /**
     * @var Collection<int, Rating>
     */
    #[ORM\OneToMany(targetEntity: Rating::class, mappedBy: 'property')]
    private Collection $ratings;

    /**
     * @var Collection<int, Preference>
     */
    #[ORM\ManyToMany(targetEntity: Preference::class, mappedBy: 'property')]
    private Collection $preferences;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $surfaceArea = null;

//    #[ORM\Column(length: 100, nullable: true, enumType: RelationshipToPropertyEnum::class)]
//    private ?RelationshipToPropertyEnum $relationshipToProperty = null;

    #[ORM\Column(nullable: true, enumType: RelationshipToPropertyEnum::class)]
    private ?RelationshipToPropertyEnum $relationshipToProperty = null;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->favoris = new ArrayCollection();
        $this->amenities = new ArrayCollection();
        $this->messengers = new ArrayCollection();
        $this->propertyReports = new ArrayCollection();
        $this->reservations = new ArrayCollection();
        $this->serviceSup = new ArrayCollection();
        $this->ratings = new ArrayCollection();
        $this->preferences = new ArrayCollection();
    }

    public function setId($id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getId()
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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTypeLocation(): ?string
    {
        return $this->typeLocation;
    }

    public function setTypeLocation(?string $typeLocation): static
    {
        $this->typeLocation = $typeLocation;

        return $this;
    }

    public function getAdress(): ?string
    {
        return $this->adress;
    }

    public function setAdress(?string $adress): static
    {
        $this->adress = $adress;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getProvince(): ?string
    {
        return $this->province;
    }

    public function setProvince(?string $province): static
    {
        $this->province = $province;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function isStatus(): ?bool
    {
        return $this->status;
    }

    public function setStatus(?bool $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getBedroom(): ?string
    {
        return $this->bedroom;
    }

    public function setBedroom(?string $bedroom): static
    {
        $this->bedroom = $bedroom;

        return $this;
    }

    public function getBathroom(): ?string
    {
        return $this->bathroom;
    }

    public function setBathroom(?string $bathroom): static
    {
        $this->bathroom = $bathroom;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * @return Collection<int, Images>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(Images $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setProperty($this);
        }

        return $this;
    }

    public function removeImage(Images $image): static
    {
        if ($this->images->removeElement($image)) {
            // set the owning side to null (unless already changed)
            if ($image->getProperty() === $this) {
                $image->setProperty(null);
            }
        }

        return $this;
    }

    public function isPublish(): ?bool
    {
        return $this->publish;
    }

    public function setPublish(bool $publish): static
    {
        $this->publish = $publish;

        return $this;
    }

    /**
     * @return Collection<int, Favoris>
     */
    public function getFavoris(): Collection
    {
        return $this->favoris;
    }

    public function addFavori(Favoris $favori): static
    {
        if (!$this->favoris->contains($favori)) {
            $this->favoris->add($favori);
            $favori->setProperty($this);
        }

        return $this;
    }

    public function removeFavori(Favoris $favori): static
    {
        if ($this->favoris->removeElement($favori)) {
            // set the owning side to null (unless already changed)
            if ($favori->getProperty() === $this) {
                $favori->setProperty(null);
            }
        }

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

    public function getUuid()
    {
        return $this->uuid;
    }

    public function setUuid($uuid): static
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getRegistrationStep(): ?int
    {
        return $this->registrationStep;
    }

    public function setRegistrationStep(int $registrationStep): static
    {
        $this->registrationStep = $registrationStep;

        return $this;
    }

    public function getPropertyProvince(): ?Province
    {
        return $this->propertyProvince;
    }

    public function setPropertyProvince(?Province $propertyProvince): static
    {
        $this->propertyProvince = $propertyProvince;

        return $this;
    }

    public function getPropertyCountry(): ?Country
    {
        return $this->propertyCountry;
    }

    public function setPropertyCountry(?Country $propertyCountry): static
    {
        $this->propertyCountry = $propertyCountry;

        return $this;
    }

    public function getGuests(): ?string
    {
        return $this->guests;
    }

    public function setGuests(?string $guests): static
    {
        $this->guests = $guests;

        return $this;
    }

    public function getLivingroom(): ?string
    {
        return $this->livingroom;
    }

    public function setLivingroom(?string $livingroom): static
    {
        $this->livingroom = $livingroom;

        return $this;
    }

    public function getPeriodicity(): ?string
    {
        return $this->periodicity;
    }

    public function setPeriodicity(?string $periodicity): static
    {
        $this->periodicity = $periodicity;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getAppartementNumber(): ?string
    {
        return $this->appartementNumber;
    }

    public function setAppartementNumber(?string $appartementNumber): static
    {
        $this->appartementNumber = $appartementNumber;

        return $this;
    }

    public function getUuidProperty(): ?string
    {
        return $this->uuidProperty;
    }

    public function setUuidProperty(?string $uuidProperty): static
    {
        $this->uuidProperty = $uuidProperty;

        return $this;
    }

    /**
     * @return Collection<int, Messenger>
     */
    public function getMessengers(): Collection
    {
        return $this->messengers;
    }

    public function addMessenger(Messenger $messenger): static
    {
        if (!$this->messengers->contains($messenger)) {
            $this->messengers->add($messenger);
            $messenger->setProperty($this);
        }

        return $this;
    }

    public function removeMessenger(Messenger $messenger): static
    {
        if ($this->messengers->removeElement($messenger)) {
            // set the owning side to null (unless already changed)
            if ($messenger->getProperty() === $this) {
                $messenger->setProperty(null);
            }
        }

        return $this;
    }

    public function getPublishAt(): ?\DateTimeInterface
    {
        return $this->publishAt;
    }

    public function setPublishAt(?\DateTimeInterface $publishAt): static
    {
        $this->publishAt = $publishAt;

        return $this;
    }

    public function getCommune(): ?Commune
    {
        return $this->commune;
    }

    public function setCommune(?Commune $commune): static
    {
        $this->commune = $commune;

        return $this;
    }

    public function isAddressView(): ?bool
    {
        return $this->addressView;
    }

    public function setAddressView(?bool $addressView): static
    {
        $this->addressView = $addressView;

        return $this;
    }

    public function isFurniture(): ?bool
    {
        return $this->furniture;
    }

    public function setFurniture(bool $furniture): static
    {
        $this->furniture = $furniture;

        return $this;
    }

    public function isAirCondition(): ?bool
    {
        return $this->airCondition;
    }

    public function setAirCondition(bool $airCondition): static
    {
        $this->airCondition = $airCondition;

        return $this;
    }

    public function isPool(): ?bool
    {
        return $this->pool;
    }

    public function setPool(bool $pool): static
    {
        $this->pool = $pool;

        return $this;
    }

    public function isOpenspaceroof(): ?bool
    {
        return $this->openspaceroof;
    }

    public function setOpenspaceroof(bool $openspaceroof): static
    {
        $this->openspaceroof = $openspaceroof;

        return $this;
    }

    public function isExteriortoilet(): ?bool
    {
        return $this->exteriortoilet;
    }

    public function setExteriortoilet(bool $exteriortoilet): static
    {
        $this->exteriortoilet = $exteriortoilet;

        return $this;
    }

    public function isSecurityguard(): ?bool
    {
        return $this->securityguard;
    }

    public function setSecurityguard(bool $securityguard): static
    {
        $this->securityguard = $securityguard;

        return $this;
    }

    public function isGarden(): ?bool
    {
        return $this->garden;
    }

    public function setGarden(bool $garden): static
    {
        $this->garden = $garden;

        return $this;
    }

    public function isWifi(): ?bool
    {
        return $this->wifi;
    }

    public function setWifi(bool $wifi): static
    {
        $this->wifi = $wifi;

        return $this;
    }

    public function isParking(): ?bool
    {
        return $this->parking;
    }

    public function setParking(bool $parking): static
    {
        $this->parking = $parking;

        return $this;
    }

    public function getPropertyStatus(): ?PropertyStatus
    {
        return $this->propertyStatus;
    }

    public function setPropertyStatus(?PropertyStatus $propertyStatus): static
    {
        $this->propertyStatus = $propertyStatus;

        return $this;
    }

    public function getUnpublishAt(): ?\DateTimeInterface
    {
        return $this->unpublishAt;
    }

    public function setUnpublishAt(?\DateTimeInterface $unpublishAt): static
    {
        $this->unpublishAt = $unpublishAt;

        return $this;
    }

    /**
     * @return Collection<int, PropertyReport>
     */
    public function getPropertyReports(): Collection
    {
        return $this->propertyReports;
    }

    public function addPropertyReport(PropertyReport $propertyReport): static
    {
        if (!$this->propertyReports->contains($propertyReport)) {
            $this->propertyReports->add($propertyReport);
            $propertyReport->setProperty($this);
        }

        return $this;
    }

    public function removePropertyReport(PropertyReport $propertyReport): static
    {
        if ($this->propertyReports->removeElement($propertyReport)) {
            // set the owning side to null (unless already changed)
            if ($propertyReport->getProperty() === $this) {
                $propertyReport->setProperty(null);
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
            $reservation->setProperty($this);
        }

        return $this;
    }

    public function removeReservation(Reservation $reservation): static
    {
        if ($this->reservations->removeElement($reservation)) {
            // set the owning side to null (unless already changed)
            if ($reservation->getProperty() === $this) {
                $reservation->setProperty(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ServiceSup>
     */
    public function getServiceSup(): Collection
    {
        return $this->serviceSup;
    }

    public function addServiceSup(ServiceSup $serviceSup): static
    {
        if (!$this->serviceSup->contains($serviceSup)) {
            $this->serviceSup->add($serviceSup);
        }

        return $this;
    }

    public function removeServiceSup(ServiceSup $serviceSup): static
    {
        $this->serviceSup->removeElement($serviceSup);

        return $this;
    }

    public function getHeader(): ?string
    {
        return $this->header;
    }

    public function setHeader(?string $header): static
    {
        $this->header = $header;

        return $this;
    }

    public function getPourcerntageOfBooking(): ?int
    {
        return $this->pourcerntageOfBooking;
    }

    public function setPourcerntageOfBooking(?int $pourcerntageOfBooking): static
    {
        $this->pourcerntageOfBooking = $pourcerntageOfBooking;

        return $this;
    }

    public function getCategory(): ?CategoryForProperty
    {
        return $this->category;
    }

    public function setCategory(?CategoryForProperty $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getPriceOfVisit(): ?string
    {
        return $this->PriceOfVisit;
    }

    public function setPriceOfVisit(?string $PriceOfVisit): static
    {
        $this->PriceOfVisit = $PriceOfVisit;

        return $this;
    }
    /**
     * @return Collection<int, Rating>
     */
    public function getRatings(): Collection
    {
        return $this->ratings;
    }

    public function addRating(Rating $rating): static
    {
        if (!$this->ratings->contains($rating)) {
            $this->ratings->add($rating);
            $rating->setProperty($this);
        }

        return $this;
    }

    public function removeRating(Rating $rating): static
    {
        if ($this->ratings->removeElement($rating)) {
            // set the owning side to null (unless already changed)
            if ($rating->getProperty() === $this) {
                $rating->setProperty(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Preference>
     */
    public function getPreferences(): Collection
    {
        return $this->preferences;
    }

    public function addPreference(Preference $preference): static
    {
        if (!$this->preferences->contains($preference)) {
            $this->preferences->add($preference);
            $preference->addProperty($this);
        }

        return $this;
    }

    public function removePreference(Preference $preference): static
    {
        if ($this->preferences->removeElement($preference)) {
            $preference->removeProperty($this);
        }

        return $this;
    }

    public function getSurfaceArea(): ?string
    {
        return $this->surfaceArea;
    }

    public function setSurfaceArea(?string $surfaceArea): static
    {
        $this->surfaceArea = $surfaceArea;

        return $this;
    }

    public function getRelationshipToProperty(): ?RelationshipToPropertyEnum
    {
        return $this->relationshipToProperty;
    }

    public function setRelationshipToProperty(?RelationshipToPropertyEnum $relationshipToProperty): static
    {
        $this->relationshipToProperty = $relationshipToProperty;

        return $this;
    }
}
