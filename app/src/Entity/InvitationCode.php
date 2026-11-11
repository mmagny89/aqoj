<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\UserRole;
use App\Repository\InvitationCodeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Code d'invitation : l'inscription est réservée aux membres de l'association Envie de Jouer.
 * Un administrateur le crée et le distribue ; il sert une fois (invitation personnelle) ou plusieurs
 * (soirée d'inscription), jusqu'à une date facultative, et peut être révoqué.
 *
 * Le compteur d'usages n'est jamais incrémenté en PHP : InvitationCodeRepository::consume() le fait
 * en un UPDATE atomique, dans la transaction qui crée le compte.
 */
#[ORM\Entity(repositoryClass: InvitationCodeRepository::class)]
#[ORM\Table(name: 'invitation_code')]
class InvitationCode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $usedCount = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        /** Forme normalisée : majuscules, sans séparateur (InvitationCodeRepository::normalize). */
        #[ORM\Column(length: 32, unique: true)]
        private string $code,
        /** Note libre de l'administrateur : « Soirée du 12 octobre », « Pour Camille ». */
        #[ORM\Column(length: 100)]
        private string $label,
        #[ORM\Column(length: 20, enumType: UserRole::class, options: ['default' => 'member'])]
        private UserRole $role,
        #[ORM\Column]
        private int $maxUses,
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $expiresAt = null,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?User $createdBy = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getLabel(): string { return $this->label; }
    public function getRole(): UserRole { return $this->role; }
    public function getMaxUses(): int { return $this->maxUses; }
    public function getUsedCount(): int { return $this->usedCount; }
    public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }
    public function getRevokedAt(): ?\DateTimeImmutable { return $this->revokedAt; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function revoke(): static { $this->revokedAt ??= new \DateTimeImmutable(); return $this; }

    /** active | exhausted | expired | revoked : ce que l'administrateur lit dans la liste. */
    public function status(\DateTimeImmutable $now = new \DateTimeImmutable()): string
    {
        return match (true) {
            $this->revokedAt !== null => 'revoked',
            $this->expiresAt !== null && $this->expiresAt <= $now => 'expired',
            $this->usedCount >= $this->maxUses => 'exhausted',
            default => 'active',
        };
    }
}
