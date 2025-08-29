<?php

namespace App\Entity;

use App\Repository\AgentCoveragesRepository;
use App\Traits\TimeStampTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AgentCoveragesRepository::class)]
#[ORM\HasLifecycleCallbacks]
class AgentCoverages
{
    use TimeStampTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'agentCoverages')]
    private Collection $agent;

    #[ORM\ManyToOne(inversedBy: 'agentCoverages')]
    private ?Country $country = null;

    /**
     * @var Collection<int, Commune>
     */
    #[ORM\ManyToMany(targetEntity: Commune::class, inversedBy: 'agentCoverages')]
    private Collection $commune;

    #[ORM\Column]
    private ?bool $active = null;

    public function __construct()
    {
        $this->agent = new ArrayCollection();
        $this->commune = new ArrayCollection();
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
            $agent->setAgentCoverages($this);
        }

        return $this;
    }

    public function removeAgent(User $agent): static
    {
        if ($this->agent->removeElement($agent)) {
            // set the owning side to null (unless already changed)
            if ($agent->getAgentCoverages() === $this) {
                $agent->setAgentCoverages(null);
            }
        }

        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): static
    {
        $this->country = $country;

        return $this;
    }

    /**
     * @return Collection<int, Commune>
     */
    public function getCommune(): Collection
    {
        return $this->commune;
    }

    public function addCommune(Commune $commune): static
    {
        if (!$this->commune->contains($commune)) {
            $this->commune->add($commune);
        }

        return $this;
    }

    public function removeCommune(Commune $commune): static
    {
        $this->commune->removeElement($commune);

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }
}
