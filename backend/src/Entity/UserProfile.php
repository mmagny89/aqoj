<?php

namespace App\Entity;

use App\Repository\UserProfileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserProfileRepository::class)]
#[ORM\Table(name: 'user_profile')]
class UserProfile implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $bggUsername;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'bggUsername' => $this->bggUsername,
            'createdAt' => $this->createdAt->format('c'),
        ];
    }

    public function getId(): ?int { return $this->id; }
    public function getBggUsername(): string { return $this->bggUsername; }
    public function setBggUsername(string $bggUsername): static { $this->bggUsername = $bggUsername; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
