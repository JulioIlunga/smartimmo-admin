<?php

namespace App\Entity;

use App\Repository\CommuneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommuneRepository::class)]
class Commune
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'communes')]
    private ?Province $province = null;

    /**
     * @var Collection<int, Property>
     */
    #[ORM\OneToMany(targetEntity: Property::class, mappedBy: 'commune')]
    private Collection $properties;

    /**
     * @var Collection<int, AgentCoverages>
     */
    #[ORM\ManyToMany(targetEntity: AgentCoverages::class, mappedBy: 'commune')]
    private Collection $agentCoverages;

    public function __construct()
    {
        $this->properties = new ArrayCollection();
        $this->agentCoverages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getProvince(): ?Province
    {
        return $this->province;
    }

    public function setProvince(?Province $province): static
    {
        $this->province = $province;

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
            $property->setCommune($this);
        }

        return $this;
    }

    public function removeProperty(Property $property): static
    {
        if ($this->properties->removeElement($property)) {
            // set the owning side to null (unless already changed)
            if ($property->getCommune() === $this) {
                $property->setCommune(null);
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
            $agentCoverage->addCommune($this);
        }

        return $this;
    }

    public function removeAgentCoverage(AgentCoverages $agentCoverage): static
    {
        if ($this->agentCoverages->removeElement($agentCoverage)) {
            $agentCoverage->removeCommune($this);
        }

        return $this;
    }
}
