<?php

namespace App\Entity;

use App\Repository\UserPreferenceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Préférences mécaniques calculées d'un utilisateur.
 *
 * Calculées depuis :
 *   - les notes BGG (signal le plus fort : bgg_user_rating >= 6)
 *   - les sessions aimées (rating = 5)
 *   - fallback : toute la collection
 *
 * Mis à jour à chaque import BGG ou nouvelle partie aimée.
 */
#[ORM\Entity(repositoryClass: UserPreferenceRepository::class)]
#[ORM\Table(name: 'user_preference')]
class UserPreference implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', onDelete: 'CASCADE')]
    private User $user;

    /** Familles de moteurs Engelstein les plus jouées */
    #[ORM\Column(type: 'json')]
    private array $topEngines = [];

    /** Familles de support les plus jouées */
    #[ORM\Column(type: 'json')]
    private array $topSupport = [];

    /** Toutes familles classées par score (moteurs + support + extra) */
    #[ORM\Column(type: 'json')]
    private array $rankedFamilies = [];

    /** Catégories BGG préférées (thèmes et types) */
    #[ORM\Column(type: 'json')]
    private array $topCategories = [];

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $user)
    {
        $this->user      = $user;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function jsonSerialize(): array
    {
        return [
            'topEngines'     => $this->topEngines,
            'topSupport'     => $this->topSupport,
            'rankedFamilies' => $this->rankedFamilies,
            'topCategories'  => $this->topCategories,
            'updatedAt'      => $this->updatedAt->format('c'),
        ];
    }

    public function getUser(): User { return $this->user; }
    public function getTopEngines(): array { return $this->topEngines; }
    public function setTopEngines(array $v): static { $this->topEngines = $v; return $this; }
    public function getTopSupport(): array { return $this->topSupport; }
    public function setTopSupport(array $v): static { $this->topSupport = $v; return $this; }
    public function getRankedFamilies(): array { return $this->rankedFamilies; }
    public function setRankedFamilies(array $v): static { $this->rankedFamilies = $v; return $this; }
    public function getTopCategories(): array { return $this->topCategories; }
    public function setTopCategories(array $v): static { $this->topCategories = $v; return $this; }
    public function setUpdatedAt(\DateTimeImmutable $dt): static { $this->updatedAt = $dt; return $this; }
}
