<?php

namespace App\Entity;

use App\Repository\GameRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameRepository::class)]
#[ORM\Table(name: 'game')]
class Game implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $bggId;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private int $minPlayers = 1;

    #[ORM\Column]
    private int $maxPlayers = 1;

    #[ORM\Column]
    private int $playingTime = 0;

    #[ORM\Column(type: 'float')]
    private float $complexity = 0.0;

    #[ORM\Column(type: 'json')]
    private array $categories = [];

    #[ORM\Column(type: 'json')]
    private array $mechanics = [];

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(nullable: true)]
    private ?int $yearPublished = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $ratingBgg = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 20, options: ['default' => 'bgg'])]
    private string $source = 'bgg';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSyncedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $bggRank = null;

    #[ORM\Column(nullable: true)]
    private ?int $usersRated = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isExpansion = false;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'bggId' => $this->bggId,
            'name' => $this->name,
            'description' => $this->description,
            'minPlayers' => $this->minPlayers,
            'maxPlayers' => $this->maxPlayers,
            'playingTime' => $this->playingTime,
            'complexity' => $this->complexity,
            'categories' => $this->categories,
            'mechanics' => $this->mechanics,
            'imageUrl' => $this->imageUrl,
            'yearPublished' => $this->yearPublished,
            'ratingBgg' => $this->ratingBgg,
            'source' => $this->source,
            'lastSyncedAt' => $this->lastSyncedAt?->format('c'),
            'bggRank' => $this->bggRank,
            'usersRated' => $this->usersRated,
            'isExpansion' => $this->isExpansion,
        ];
    }

    public function getId(): ?int { return $this->id; }
    public function getBggId(): string { return $this->bggId; }
    public function setBggId(string $bggId): static { $this->bggId = $bggId; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getMinPlayers(): int { return $this->minPlayers; }
    public function setMinPlayers(int $minPlayers): static { $this->minPlayers = $minPlayers; return $this; }
    public function getMaxPlayers(): int { return $this->maxPlayers; }
    public function setMaxPlayers(int $maxPlayers): static { $this->maxPlayers = $maxPlayers; return $this; }
    public function getPlayingTime(): int { return $this->playingTime; }
    public function setPlayingTime(int $playingTime): static { $this->playingTime = $playingTime; return $this; }
    public function getComplexity(): float { return $this->complexity; }
    public function setComplexity(float $complexity): static { $this->complexity = $complexity; return $this; }
    public function getCategories(): array { return $this->categories; }
    public function setCategories(array $categories): static { $this->categories = $categories; return $this; }
    public function getMechanics(): array { return $this->mechanics; }
    public function setMechanics(array $mechanics): static { $this->mechanics = $mechanics; return $this; }
    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $imageUrl): static { $this->imageUrl = $imageUrl; return $this; }
    public function getYearPublished(): ?int { return $this->yearPublished; }
    public function setYearPublished(?int $yearPublished): static { $this->yearPublished = $yearPublished; return $this; }
    public function getRatingBgg(): ?float { return $this->ratingBgg; }
    public function setRatingBgg(?float $ratingBgg): static { $this->ratingBgg = $ratingBgg; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getSource(): string { return $this->source; }
    public function setSource(string $source): static { $this->source = $source; return $this; }
    public function getLastSyncedAt(): ?\DateTimeImmutable { return $this->lastSyncedAt; }
    public function setLastSyncedAt(\DateTimeImmutable $dt): static { $this->lastSyncedAt = $dt; return $this; }
    public function getBggRank(): ?int { return $this->bggRank; }
    public function setBggRank(?int $bggRank): static { $this->bggRank = $bggRank; return $this; }
    public function getUsersRated(): ?int { return $this->usersRated; }
    public function setUsersRated(?int $usersRated): static { $this->usersRated = $usersRated; return $this; }
    public function isExpansion(): bool { return $this->isExpansion; }
    public function setIsExpansion(bool $isExpansion): static { $this->isExpansion = $isExpansion; return $this; }
}
