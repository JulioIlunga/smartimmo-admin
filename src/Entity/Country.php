<?php

namespace App\Entity;

use App\Repository\CountryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CountryRepository::class)]
class Country
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $code = null;

    #[ORM\Column(length: 100)]
    private ?string $countryCode = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $modifiedAt = null;

    #[ORM\Column(nullable: true)]
    private ?bool $activated = null;

    #[ORM\Column(nullable: true)]
    private ?bool $mobileMoney = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $isoCode = null;

    #[ORM\OneToMany(mappedBy: 'country', targetEntity: Province::class)]
    private Collection $provinces;

    /**
     * @var Collection<int, Property>
     */
    #[ORM\OneToMany(targetEntity: Property::class, mappedBy: 'propertyCountry')]
    private Collection $properties;

    /**
     * @var Collection<int, AgentCoverages>
     */
    #[ORM\OneToMany(targetEntity: AgentCoverages::class, mappedBy: 'country')]
    private Collection $agentCoverages;

    public function __construct()
    {
        $this->provinces = new ArrayCollection();
        $this->properties = new ArrayCollection();
        $this->agentCoverages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function setCountryCode(string $countryCode): self
    {
        $this->countryCode = $countryCode;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getModifiedAt(): ?\DateTimeInterface
    {
        return $this->modifiedAt;
    }

    public function setModifiedAt(?\DateTimeInterface $modifiedAt): self
    {
        $this->modifiedAt = $modifiedAt;

        return $this;
    }

    public function isActivated(): ?bool
    {
        return $this->activated;
    }

    public function setActivated(?bool $activated): self
    {
        $this->activated = $activated;

        return $this;
    }

    public function isMobileMoney(): ?bool
    {
        return $this->mobileMoney;
    }

    public function setMobileMoney(?bool $mobileMoney): self
    {
        $this->mobileMoney = $mobileMoney;

        return $this;
    }

    public function getIsoCode(): ?string
    {
        return $this->isoCode;
    }

    public function setIsoCode(?string $isoCode): self
    {
        $this->isoCode = $isoCode;

        return $this;
    }

    /**
     * @return Collection<int, Province>
     */
    public function getProvinces(): Collection
    {
        return $this->provinces;
    }

    public function addProvince(Province $province): self
    {
        if (!$this->provinces->contains($province)) {
            $this->provinces->add($province);
            $province->setCountry($this);
        }

        return $this;
    }

    public function removeProvince(Province $province): self
    {
        if ($this->provinces->removeElement($province)) {
            // set the owning side to null (unless already changed)
            if ($province->getCountry() === $this) {
                $province->setCountry(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Property>
     */
    public function getProperties(): Collection
    {
        return $this->properties;
    }

    public function addProperty(Property $property): static
    {
        if (!$this->properties->contains($property)) {
            $this->properties->add($property);
            $property->setPropertyCountry($this);
        }

        return $this;
    }

    public function removeProperty(Property $property): static
    {
        if ($this->properties->removeElement($property)) {
            // set the owning side to null (unless already changed)
            if ($property->getPropertyCountry() === $this) {
                $property->setPropertyCountry(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AgentCoverages>
     */
    public function getAgentCoverages(): Collection
    {
        return $this->agentCoverages;
    }

    public function addAgentCoverage(AgentCoverages $agentCoverage): static
    {
        if (!$this->agentCoverages->contains($agentCoverage)) {
            $this->agentCoverages->add($agentCoverage);
            $agentCoverage->setCountry($this);
        }

        return $this;
    }

    public function removeAgentCoverage(AgentCoverages $agentCoverage): static
    {
        if ($this->agentCoverages->removeElement($agentCoverage)) {
            // set the owning side to null (unless already changed)
            if ($agentCoverage->getCountry() === $this) {
                $agentCoverage->setCountry(null);
            }
        }

        return $this;
    }
}
