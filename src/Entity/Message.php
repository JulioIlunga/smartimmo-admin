<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use App\Traits\TimeStampTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Message
{
    use TimeStampTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    private ?Messenger $messenger = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $message = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    private ?User $whoSent = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessenger(): ?Messenger
    {
        return $this->messenger;
    }

    public function setMessenger(?Messenger $messenger): static
    {
        $this->messenger = $messenger;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getWhoSent(): ?User
    {
        return $this->whoSent;
    }

    public function setWhoSent(?User $whoSent): static
    {
        $this->whoSent = $whoSent;

        return $this;
    }

}
