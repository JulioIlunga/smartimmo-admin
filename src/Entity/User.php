<?php

namespace App\Entity;

use App\Repository\UserRepository;
use App\Traits\TimeStampTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_PHONE', fields: ['phone'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(
    fields: ['phone'],
    message: 'Ce numéro de téléphone est déjà associé à un autre compte.',
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use TimeStampTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $phone = null;

    #[ORM\ManyToOne(targetEntity: 'Role', inversedBy: 'users')]
    private $role;

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $phonecode = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $firstname = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(nullable: true)]
    private ?bool $termsAndCondition = null;

    #[ORM\Column(nullable: true)]
    private ?int $userType = null;

    #[ORM\Column]
    private ?bool $accountConfirmed = null;

    #[ORM\Column]
    private ?bool $active = null;

    #[ORM\Column]
    private ?bool $isAgent = null;

    /**
     * @var Collection<int, Favoris>
     */
    #[ORM\OneToMany(targetEntity: Favoris::class, mappedBy: 'user')]
    private Collection $favoris;

    /**
     * @var Collection<int, Property>
     */
    #[ORM\OneToMany(targetEntity: Property::class, mappedBy: 'user')]
    private Collection $properties;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $picture = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $sex = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dob = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $Address = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $agentPhone = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $agentPhoneCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fbckLink = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $instaGLink = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $linkedInLink = null;

    /**
     * @var Collection<int, Message>
     */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'whoSent')]
    private Collection $messages;

    #[ORM\Column(length: 100)]
    private ?string $code = null;

    /**
     * @var Collection<int, Messenger>
     */
    #[ORM\OneToMany(targetEntity: Messenger::class, mappedBy: 'receiver')]
    private Collection $messengers;

    /**
     * @var Collection<int, Messenger>
     */
    #[ORM\OneToMany(targetEntity: Messenger::class, mappedBy: 'sender')]
    private Collection $messengersSender;

    /**
     * @var Collection<int, Messenger>
     */
    #[ORM\OneToMany(targetEntity: Messenger::class, mappedBy: 'agent')]
    private Collection $agentMessengers;

    /**
     * @var Collection<int, Messenger>
     */
    #[ORM\OneToMany(targetEntity: Messenger::class, mappedBy: 'client')]
    private Collection $clientMessengers;

    /**
     * @var Collection<int, AccountResetPassword>
     */
    #[ORM\OneToMany(targetEntity: AccountResetPassword::class, mappedBy: 'user')]
    private Collection $accountResetPasswords;

    /**
     * @var Collection<int, AgencyAgent>
     */
    #[ORM\OneToMany(targetEntity: AgencyAgent::class, mappedBy: 'agent')]
    private Collection $agencyAgents;

    #[ORM\ManyToOne(inversedBy: 'users')]
    private ?Agency $agency = null;

    /**
     * @var Collection<int, Agency>
     */
    #[ORM\OneToMany(targetEntity: Agency::class, mappedBy: 'owner')]
    private Collection $agencies;

    #[ORM\Column(nullable: true)]
    private ?bool $smartimmoAdministrator = null;

    /**
     * @var Collection<int, PropertyReport>
     */
    #[ORM\OneToMany(targetEntity: PropertyReport::class, mappedBy: 'user')]
    private Collection $propertyReports;

    #[ORM\Column(nullable: true)]
    private ?bool $block = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $blockReason = null;

    /**
     * @var Collection<int, Reservation>
     */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'user')]
    private Collection $reservations;

    /**
     * @var Collection<int, Invoice>
     */
    #[ORM\OneToMany(targetEntity: Invoice::class, mappedBy: 'user')]
    private Collection $invoices;

    /**
     * @var Collection<int, Preference>
     */
    #[ORM\OneToMany(targetEntity: Preference::class, mappedBy: 'user')]
    private Collection $preferences;

    /**
     * @var Collection<int, Rating>
     */
    #[ORM\OneToMany(targetEntity: Rating::class, mappedBy: 'user')]
    private Collection $ratings;

    /**
     * @var Collection<int, Rating>
     */
    #[ORM\OneToMany(targetEntity: Rating::class, mappedBy: 'agent')]
    private Collection $ratingsAgent;

    /**
     * @var Collection<int, PaymentForPreference>
     */
    #[ORM\OneToMany(targetEntity: PaymentForPreference::class, mappedBy: 'user')]
    private Collection $paymentForPreferences;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastAction = null;

    #[ORM\Column(nullable: true)]
    private ?bool $topAgent = null;

    #[ORM\ManyToOne(inversedBy: 'agent')]
    private ?Subscriptions $subscriptions = null;

    #[ORM\ManyToOne(cascade: ['all'], fetch: 'EAGER', inversedBy: 'agent')]
    private ?CreditWallet $creditWallet = null;

    /**
     * @var Collection<int, LeadClaims>
     */
    #[ORM\OneToMany(targetEntity: LeadClaims::class, mappedBy: 'agent')]
    private Collection $leadClaims;

    #[ORM\ManyToOne(inversedBy: 'agent')]
    private ?AgentCoverages $agentCoverages = null;

    /**
     * @var Collection<int, PaymentForMembership>
     */
    #[ORM\OneToMany(targetEntity: PaymentForMembership::class, mappedBy: 'user', cascade: ['all'], fetch: 'EAGER')]
    private Collection $paymentForMemberships;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $salutation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deactivatedAt = null;

    /**
     * @var Collection<int, Province>
     */
    #[ORM\ManyToMany(targetEntity: Province::class, inversedBy: 'users')]
    private Collection $coveredCities;

    public function __construct()
    {
        $this->favoris = new ArrayCollection();
        $this->properties = new ArrayCollection();
        $this->messages = new ArrayCollection();
        $this->messengers = new ArrayCollection();
        $this->messengersSender = new ArrayCollection();
        $this->agentMessengers = new ArrayCollection();
        $this->clientMessengers = new ArrayCollection();
        $this->accountResetPasswords = new ArrayCollection();
        $this->agencyAgents = new ArrayCollection();
        $this->agencies = new ArrayCollection();
        $this->propertyReports = new ArrayCollection();
        $this->reservations = new ArrayCollection();
        $this->invoices = new ArrayCollection();
        $this->preferences = new ArrayCollection();
        $this->ratings = new ArrayCollection();
        $this->ratingsAgent = new ArrayCollection();
        $this->paymentForPreferences = new ArrayCollection();
        $this->leadClaims = new ArrayCollection();
        $this->paymentForMemberships = new ArrayCollection();
        $this->coveredCities = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->phone;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $rs = [];

//        $rs[] = 'ROLE_CUSTOMER';

        if ($this->role != null) {
            foreach ($this->role->getPrivilege() as $role)
            {
                $rs[] = 'ROLE_'.$role->getName();
            }
        }

        return $rs;
    }


//    public function getRole()
//    {
//        return $this->role;
//    }
//
    public function setRole($role): void
    {
        $this->role = $role;
    }

//    /**
//     * @see UserInterface
//     */
//    public function getRoles(): array
//    {
//        return [$this->role->getRole()];
//    }


    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getPhonecode(): ?string
    {
        return $this->phonecode;
    }

    public function setPhonecode(?string $phonecode): static
    {
        $this->phonecode = $phonecode;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(?string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function isTermsAndCondition(): ?bool
    {
        return $this->termsAndCondition;
    }

    public function setTermsAndCondition(?bool $termsAndCondition): static
    {
        $this->termsAndCondition = $termsAndCondition;

        return $this;
    }

    public function getUserType(): ?int
    {
        return $this->userType;
    }

    public function setUserType(?int $userType): static
    {
        $this->userType = $userType;

        return $this;
    }

    public function isAccountConfirmed(): ?bool
    {
        return $this->accountConfirmed;
    }

    public function setAccountConfirmed(bool $accountConfirmed): static
    {
        $this->accountConfirmed = $accountConfirmed;

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

    public function isAgent(): ?bool
    {
        return $this->isAgent;
    }

    public function setAgent(bool $isAgent): static
    {
        $this->isAgent = $isAgent;

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
            $favori->setUser($this);
        }

        return $this;
    }

    public function removeFavori(Favoris $favori): static
    {
        if ($this->favoris->removeElement($favori)) {
            // set the owning side to null (unless already changed)
            if ($favori->getUser() === $this) {
                $favori->setUser(null);
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
            $property->setUser($this);
        }

        return $this;
    }

    public function removeProperty(Property $property): static
    {
        if ($this->properties->removeElement($property)) {
            // set the owning side to null (unless already changed)
            if ($property->getUser() === $this) {
                $property->setUser(null);
            }
        }

        return $this;
    }

    public function getPicture(): ?string
    {
        return $this->picture;
    }

    public function setPicture(?string $picture): static
    {
        $this->picture = $picture;

        return $this;
    }

    public function getSex(): ?string
    {
        return $this->sex;
    }

    public function setSex(?string $sex): static
    {
        $this->sex = $sex;

        return $this;
    }

    public function getDob(): ?\DateTimeInterface
    {
        return $this->dob;
    }

    public function setDob(?\DateTimeInterface $dob): static
    {
        $this->dob = $dob;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->Address;
    }

    public function setAddress(?string $Address): static
    {
        $this->Address = $Address;

        return $this;
    }

    public function getAgentPhone(): ?string
    {
        return $this->agentPhone;
    }

    public function setAgentPhone(?string $agentPhone): static
    {
        $this->agentPhone = $agentPhone;

        return $this;
    }

    public function getAgentPhoneCode(): ?string
    {
        return $this->agentPhoneCode;
    }

    public function setAgentPhoneCode(?string $agentPhoneCode): static
    {
        $this->agentPhoneCode = $agentPhoneCode;

        return $this;
    }

    public function getFbckLink(): ?string
    {
        return $this->fbckLink;
    }

    public function setFbckLink(?string $fbckLink): static
    {
        $this->fbckLink = $fbckLink;

        return $this;
    }

    public function getInstaGLink(): ?string
    {
        return $this->instaGLink;
    }

    public function setInstaGLink(?string $instaGLink): static
    {
        $this->instaGLink = $instaGLink;

        return $this;
    }

    public function getLinkedInLink(): ?string
    {
        return $this->linkedInLink;
    }

    public function setLinkedInLink(?string $linkedInLink): static
    {
        $this->linkedInLink = $linkedInLink;

        return $this;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setWhoSent($this);
        }

        return $this;
    }

    public function removeMessage(Message $message): static
    {
        if ($this->messages->removeElement($message)) {
            // set the owning side to null (unless already changed)
            if ($message->getWhoSent() === $this) {
                $message->setWhoSent(null);
            }
        }

        return $this;
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
            $messenger->setReceiver($this);
        }

        return $this;
    }

    public function removeMessenger(Messenger $messenger): static
    {
        if ($this->messengers->removeElement($messenger)) {
            // set the owning side to null (unless already changed)
            if ($messenger->getReceiver() === $this) {
                $messenger->setReceiver(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Messenger>
     */
    public function getMessengersSender(): Collection
    {
        return $this->messengersSender;
    }

    public function addMessengersSender(Messenger $messengersSender): static
    {
        if (!$this->messengersSender->contains($messengersSender)) {
            $this->messengersSender->add($messengersSender);
            $messengersSender->setSender($this);
        }

        return $this;
    }

    public function removeMessengersSender(Messenger $messengersSender): static
    {
        if ($this->messengersSender->removeElement($messengersSender)) {
            // set the owning side to null (unless already changed)
            if ($messengersSender->getSender() === $this) {
                $messengersSender->setSender(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Messenger>
     */
    public function getAgentMessengers(): Collection
    {
        return $this->agentMessengers;
    }

    public function addAgentMessenger(Messenger $agentMessenger): static
    {
        if (!$this->agentMessengers->contains($agentMessenger)) {
            $this->agentMessengers->add($agentMessenger);
            $agentMessenger->setAgent($this);
        }

        return $this;
    }

    public function removeAgentMessenger(Messenger $agentMessenger): static
    {
        if ($this->agentMessengers->removeElement($agentMessenger)) {
            // set the owning side to null (unless already changed)
            if ($agentMessenger->getAgent() === $this) {
                $agentMessenger->setAgent(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Messenger>
     */
    public function getClientMessengers(): Collection
    {
        return $this->clientMessengers;
    }

    public function addClientMessenger(Messenger $clientMessenger): static
    {
        if (!$this->clientMessengers->contains($clientMessenger)) {
            $this->clientMessengers->add($clientMessenger);
            $clientMessenger->setClient($this);
        }

        return $this;
    }

    public function removeClientMessenger(Messenger $clientMessenger): static
    {
        if ($this->clientMessengers->removeElement($clientMessenger)) {
            // set the owning side to null (unless already changed)
            if ($clientMessenger->getClient() === $this) {
                $clientMessenger->setClient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AccountResetPassword>
     */
    public function getAccountResetPasswords(): Collection
    {
        return $this->accountResetPasswords;
    }

    public function addAccountResetPassword(AccountResetPassword $accountResetPassword): static
    {
        if (!$this->accountResetPasswords->contains($accountResetPassword)) {
            $this->accountResetPasswords->add($accountResetPassword);
            $accountResetPassword->setUser($this);
        }

        return $this;
    }

    public function removeAccountResetPassword(AccountResetPassword $accountResetPassword): static
    {
        if ($this->accountResetPasswords->removeElement($accountResetPassword)) {
            // set the owning side to null (unless already changed)
            if ($accountResetPassword->getUser() === $this) {
                $accountResetPassword->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AgencyAgent>
     */
    public function getAgencyAgents(): Collection
    {
        return $this->agencyAgents;
    }

    public function addAgencyAgent(AgencyAgent $agencyAgent): static
    {
        if (!$this->agencyAgents->contains($agencyAgent)) {
            $this->agencyAgents->add($agencyAgent);
            $agencyAgent->setAgent($this);
        }

        return $this;
    }

    public function removeAgencyAgent(AgencyAgent $agencyAgent): static
    {
        if ($this->agencyAgents->removeElement($agencyAgent)) {
            // set the owning side to null (unless already changed)
            if ($agencyAgent->getAgent() === $this) {
                $agencyAgent->setAgent(null);
            }
        }

        return $this;
    }

    public function getAgency(): ?Agency
    {
        return $this->agency;
    }

    public function setAgency(?Agency $agency): static
    {
        $this->agency = $agency;

        return $this;
    }

    /**
     * @return Collection<int, Agency>
     */
    public function getAgencies(): Collection
    {
        return $this->agencies;
    }

    public function addAgency(Agency $agency): static
    {
        if (!$this->agencies->contains($agency)) {
            $this->agencies->add($agency);
            $agency->setOwner($this);
        }

        return $this;
    }

    public function removeAgency(Agency $agency): static
    {
        if ($this->agencies->removeElement($agency)) {
            // set the owning side to null (unless already changed)
            if ($agency->getOwner() === $this) {
                $agency->setOwner(null);
            }
        }

        return $this;
    }

    public function isSmartimmoAdministrator(): ?bool
    {
        return $this->smartimmoAdministrator;
    }

    public function setSmartimmoAdministrator(?bool $smartimmoAdministrator): static
    {
        $this->smartimmoAdministrator = $smartimmoAdministrator;

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
            $propertyReport->setUser($this);
        }

        return $this;
    }

    public function removePropertyReport(PropertyReport $propertyReport): static
    {
        if ($this->propertyReports->removeElement($propertyReport)) {
            // set the owning side to null (unless already changed)
            if ($propertyReport->getUser() === $this) {
                $propertyReport->setUser(null);
            }
        }

        return $this;
    }

    public function isBlock(): ?bool
    {
        return $this->block;
    }

    public function setBlock(?bool $block): static
    {
        $this->block = $block;

        return $this;
    }

    public function getBlockReason(): ?string
    {
        return $this->blockReason;
    }

    public function setBlockReason(?string $blockReason): static
    {
        $this->blockReason = $blockReason;

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
            $reservation->setUser($this);
        }

        return $this;
    }

    public function removeReservation(Reservation $reservation): static
    {
        if ($this->reservations->removeElement($reservation)) {
            // set the owning side to null (unless already changed)
            if ($reservation->getUser() === $this) {
                $reservation->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function getInvoices(): Collection
    {
        return $this->invoices;
    }

    public function addInvoice(Invoice $invoice): static
    {
        if (!$this->invoices->contains($invoice)) {
            $this->invoices->add($invoice);
            $invoice->setUser($this);
        }

        return $this;
    }

    public function removeInvoice(Invoice $invoice): static
    {
        if ($this->invoices->removeElement($invoice)) {
            // set the owning side to null (unless already changed)
            if ($invoice->getUser() === $this) {
                $invoice->setUser(null);
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
            $preference->setUser($this);
        }

        return $this;
    }

    public function removePreference(Preference $preference): static
    {
        if ($this->preferences->removeElement($preference)) {
            // set the owning side to null (unless already changed)
            if ($preference->getUser() === $this) {
                $preference->setUser(null);
            }
        }

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
            $rating->setUser($this);
        }

        return $this;
    }

    public function removeRating(Rating $rating): static
    {
        if ($this->ratings->removeElement($rating)) {
            // set the owning side to null (unless already changed)
            if ($rating->getUser() === $this) {
                $rating->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Rating>
     */
    public function getRatingsAgent(): Collection
    {
        return $this->ratingsAgent;
    }

    public function addRatingsAgent(Rating $ratingsAgent): static
    {
        if (!$this->ratingsAgent->contains($ratingsAgent)) {
            $this->ratingsAgent->add($ratingsAgent);
            $ratingsAgent->setAgent($this);
        }

        return $this;
    }

    public function removeRatingsAgent(Rating $ratingsAgent): static
    {
        if ($this->ratingsAgent->removeElement($ratingsAgent)) {
            // set the owning side to null (unless already changed)
            if ($ratingsAgent->getAgent() === $this) {
                $ratingsAgent->setAgent(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, PaymentForPreference>
     */
    public function getPaymentForPreferences(): Collection
    {
        return $this->paymentForPreferences;
    }

    public function addPaymentForPreference(PaymentForPreference $paymentForPreference): static
    {
        if (!$this->paymentForPreferences->contains($paymentForPreference)) {
            $this->paymentForPreferences->add($paymentForPreference);
            $paymentForPreference->setUser($this);
        }

        return $this;
    }

    public function removePaymentForPreference(PaymentForPreference $paymentForPreference): static
    {
        if ($this->paymentForPreferences->removeElement($paymentForPreference)) {
            // set the owning side to null (unless already changed)
            if ($paymentForPreference->getUser() === $this) {
                $paymentForPreference->setUser(null);
            }
        }

        return $this;
    }

    public function getLastAction(): ?\DateTimeInterface
    {
        return $this->lastAction;
    }

    public function setLastAction(?\DateTimeInterface $lastAction): static
    {
        $this->lastAction = $lastAction;

        return $this;
    }

    public function isTopAgent(): ?bool
    {
        return $this->topAgent;
    }

    public function setTopAgent(?bool $topAgent): static
    {
        $this->topAgent = $topAgent;

        return $this;
    }

    public function getSubscriptions(): ?Subscriptions
    {
        return $this->subscriptions;
    }

    public function setSubscriptions(?Subscriptions $subscriptions): static
    {
        $this->subscriptions = $subscriptions;

        return $this;
    }

    public function getCreditWallet(): ?CreditWallet
    {
        return $this->creditWallet;
    }

    public function setCreditWallet(?CreditWallet $creditWallet): static
    {
        $this->creditWallet = $creditWallet;

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
            $leadClaim->setAgent($this);
        }

        return $this;
    }

    public function removeLeadClaim(LeadClaims $leadClaim): static
    {
        if ($this->leadClaims->removeElement($leadClaim)) {
            // set the owning side to null (unless already changed)
            if ($leadClaim->getAgent() === $this) {
                $leadClaim->setAgent(null);
            }
        }

        return $this;
    }

    public function getAgentCoverages(): ?AgentCoverages
    {
        return $this->agentCoverages;
    }

    public function setAgentCoverages(?AgentCoverages $agentCoverages): static
    {
        $this->agentCoverages = $agentCoverages;

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
            $paymentForMembership->setUser($this);
        }

        return $this;
    }

    public function removePaymentForMembership(PaymentForMembership $paymentForMembership): static
    {
        if ($this->paymentForMemberships->removeElement($paymentForMembership)) {
            // set the owning side to null (unless already changed)
            if ($paymentForMembership->getUser() === $this) {
                $paymentForMembership->setUser(null);
            }
        }

        return $this;
    }

    public function getSalutation(): ?string
    {
        return $this->salutation;
    }

    public function setSalutation(?string $salutation): static
    {
        $this->salutation = $salutation;

        return $this;
    }

    public function getDeactivatedAt(): ?\DateTimeInterface
    {
        return $this->deactivatedAt;
    }

    public function setDeactivatedAt(?\DateTimeInterface $deactivatedAt): static
    {
        $this->deactivatedAt = $deactivatedAt;

        return $this;
    }

    /**
     * @return Collection<int, Province>
     */
    public function getCoveredCities(): Collection
    {
        return $this->coveredCities;
    }

    public function addCoveredCity(Province $coveredCity): static
    {
        if (!$this->coveredCities->contains($coveredCity)) {
            $this->coveredCities->add($coveredCity);
        }

        return $this;
    }

    public function removeCoveredCity(Province $coveredCity): static
    {
        $this->coveredCities->removeElement($coveredCity);

        return $this;
    }
}
