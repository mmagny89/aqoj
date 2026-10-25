<?php

namespace App\Entity;

use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
class User implements \JsonSerializable, UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $passwordHash;

    #[ORM\Column(length: 100, nullable: true, unique: true)]
    private ?string $bggUsername = null;

    #[ORM\ManyToMany(targetEntity: Game::class)]
    #[ORM\JoinTable(name: 'user_game')]
    private Collection $games;

    #[ORM\Column(options: ['default' => false])]
    private bool $isAdmin = false;

    #[ORM\Column(length: 20, enumType: UserRole::class, options: ['default' => 'member'])]
    private UserRole $role = UserRole::Member;

    /** Le code d'invitation avec lequel le compte a été créé (null : compte antérieur ou créé en ligne de commande). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?InvitationCode $invitationCode = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return array_values(array_unique([
            'ROLE_USER',
            ...$this->role->securityRoles(),
            ...($this->isAdmin ? ['ROLE_ADMIN'] : []),
        ]));
    }

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->games = new ArrayCollection();
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'bggUsername' => $this->bggUsername,
            'isAdmin' => $this->isAdmin,
            'role' => $this->role->value,
            'createdAt' => $this->createdAt->format('c'),
        ];
    }

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }
    public function getPasswordHash(): string { return $this->passwordHash; }
    public function setPasswordHash(string $hash): static { $this->passwordHash = $hash; return $this; }
    public function getBggUsername(): ?string { return $this->bggUsername; }
    public function setBggUsername(?string $bggUsername): static { $this->bggUsername = $bggUsername; return $this; }
    public function getGames(): Collection { return $this->games; }
    public function addGame(Game $game): static { if (!$this->games->contains($game)) { $this->games->add($game); } return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function isAdmin(): bool { return $this->isAdmin; }
    public function setIsAdmin(bool $v): static { $this->isAdmin = $v; return $this; }
    public function getRole(): UserRole { return $this->role; }
    public function setRole(UserRole $role): static { $this->role = $role; return $this; }
    public function isVip(): bool { return $this->role === UserRole::Vip; }
    public function getInvitationCode(): ?InvitationCode { return $this->invitationCode; }
    public function setInvitationCode(?InvitationCode $code): static { $this->invitationCode = $code; return $this; }
}
