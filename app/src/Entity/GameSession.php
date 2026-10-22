<?php

namespace App\Entity;

use App\Repository\GameSessionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameSessionRepository::class)]
#[ORM\Table(name: 'game_session')]
class GameSession implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Game::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Game $game;

    #[ORM\Column]
    private int $playersCount;

    #[ORM\Column(nullable: true)]
    private ?int $rating = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column]
    private \DateTimeImmutable $playedAt;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $context = null;

    public function __construct()
    {
        $this->playedAt = new \DateTimeImmutable();
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'game' => $this->game,
            'playersCount' => $this->playersCount,
            'rating' => $this->rating,
            'note' => $this->note,
            'playedAt' => $this->playedAt->format('c'),
            'context' => $this->context,
        ];
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getGame(): Game { return $this->game; }
    public function setGame(Game $game): static { $this->game = $game; return $this; }
    public function getPlayersCount(): int { return $this->playersCount; }
    public function setPlayersCount(int $playersCount): static { $this->playersCount = $playersCount; return $this; }
    public function getRating(): ?int { return $this->rating; }
    public function setRating(?int $rating): static { $this->rating = $rating; return $this; }
    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $note): static { $this->note = $note; return $this; }
    public function getPlayedAt(): \DateTimeImmutable { return $this->playedAt; }
    public function setPlayedAt(\DateTimeImmutable $playedAt): static { $this->playedAt = $playedAt; return $this; }
    public function getContext(): ?string { return $this->context; }
    public function setContext(?string $context): static { $this->context = $context; return $this; }
}
