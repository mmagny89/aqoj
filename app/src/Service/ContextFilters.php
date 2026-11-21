<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Filtres de contexte (« qu'est-ce qu'on joue maintenant, avec ces gens et ce temps ? »), communs à la ludothèque,
 * aux recommandations et à la recherche. Tout vient du catalogue déjà importé : aucun appel à BGG.
 *
 * Un jeu sans donnée (poids, durée, âge, mécaniques, sondage de joueurs) n'est jamais écarté : il reste visible et le front
 * l'étiquette « inconnu ». Les conditions sont du SQL natif sur la table `game` aliasée.
 */
final readonly class ContextFilters
{
    /** Ajustement au nombre de joueurs : `best` = idéal (sondage BGG), `recommended` = conseillé ; absent = simplement jouable. */
    public const FITS = ['best', 'recommended'];

    /** Mécaniques BGG d'un jeu coopératif (« Cooperative Game » sur BGG, « Cooperative » dans les données de départ). */
    private const COOP_MECHANICS = ['Cooperative Game', 'Cooperative'];

    public function __construct(
        public ?int $players = null,
        public ?int $maxTime = null,
        public ?string $fit = null,
        public ?string $weight = null,
        public ?bool $coop = null,
        public ?int $age = null,
        public ?bool $played = null,
    ) {}

    /** `$withPlayed` : faux là où « joué » est déjà un onglet (ludothèque). */
    public static function fromRequest(Request $request, bool $withPlayed = true): self
    {
        $fit    = $request->query->getString('fit');
        $weight = $request->query->getString('weight');
        $flag   = static fn(string $key): ?bool => $request->query->has($key) && $request->query->getString($key) !== ''
            ? $request->query->getBoolean($key)
            : null;

        return new self(
            players: $request->query->getInt('players') ?: null,
            maxTime: $request->query->getInt('maxTime') ?: null,
            fit: in_array($fit, self::FITS, true) ? $fit : null,
            weight: isset(RecommendationService::DIFFICULTY_BANDS[$weight]) ? $weight : null,
            coop: $flag('coop'),
            age: $request->query->getInt('age') ?: null,
            played: $withPlayed ? $flag('played') : null,
        );
    }

    /** Vrai si un filtre au-delà du nombre de joueurs et de la durée est posé. */
    public function isActive(): bool
    {
        return $this->fit !== null || $this->weight !== null || $this->coop !== null || $this->age !== null || $this->played !== null;
    }

    /** Vrai si le moindre filtre de contexte est posé (joueurs et durée compris). */
    public function hasAny(): bool
    {
        return $this->isActive() || $this->players !== null || $this->maxTime !== null;
    }

    /**
     * @param bool $basics  ajoute aussi joueurs (jouable) et durée, pour les appelants qui ne les posent pas eux-mêmes
     *
     * @return array{0: string[], 1: array<int, mixed>} conditions SQL (alias `g`, paramètres `?`) et leurs valeurs, dans l'ordre
     */
    public function conditions(?int $userId, bool $basics = false): array
    {
        $conditions = [];
        $params     = [];

        if ($basics && $this->players !== null) {
            $conditions[] = 'g.min_players <= ? AND g.max_players >= ?';
            array_push($params, $this->players, $this->players);
        }
        if ($basics && $this->maxTime !== null) {
            $conditions[] = '(g.playing_time <= ? OR g.playing_time = 0)';
            $params[] = $this->maxTime;
        }

        if ($this->fit !== null && $this->players !== null) {
            $column = $this->fit === 'best' ? 'g.best_players' : 'g.recommended_players';
            $conditions[] = "({$column} IS NULL OR {$column}::jsonb @> ?::jsonb)";
            $params[] = json_encode([$this->players]);
        }

        if ($this->weight !== null) {
            [$min, $max] = RecommendationService::DIFFICULTY_BANDS[$this->weight];
            $range = ['g.complexity > ?'];
            $params[] = $min ?? 0;
            if ($max !== null) {
                $range[] = 'g.complexity <= ?';
                $params[] = $max;
            }
            // 0 = poids inconnu : le jeu reste visible
            $conditions[] = '(g.complexity = 0 OR (' . implode(' AND ', $range) . '))';
        }

        if ($this->coop !== null) {
            $coop = '(' . implode(' OR ', array_fill(0, count(self::COOP_MECHANICS), 'g.mechanics::jsonb @> ?::jsonb')) . ')';
            // pas de mécaniques = fiche pas encore enrichie : coopératif ou non, on ne sait pas
            $conditions[] = "(g.mechanics::text = '[]' OR " . ($this->coop ? $coop : "NOT {$coop}") . ')';
            foreach (self::COOP_MECHANICS as $mechanic) {
                $params[] = json_encode([$mechanic]);
            }
        }

        if ($this->age !== null) {
            $conditions[] = '(g.min_age IS NULL OR g.min_age <= ?)';
            $params[] = $this->age;
        }

        if ($this->played !== null && $userId !== null) {
            $played = '(EXISTS (SELECT 1 FROM user_game ugp WHERE ugp.user_id = ? AND ugp.game_id = g.id AND ugp.bgg_user_rating IS NOT NULL)
                OR EXISTS (SELECT 1 FROM game_session gsp WHERE gsp.user_id = ? AND gsp.game_id = g.id))';
            $conditions[] = $this->played ? $played : "NOT {$played}";
            array_push($params, $userId, $userId);
        }

        return [$conditions, $params];
    }
}
