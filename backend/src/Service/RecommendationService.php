<?php

namespace App\Service;

use App\Entity\Game;
use App\Entity\User;
use App\Repository\GameRepository;
use App\Repository\GameSessionRepository;
use App\Repository\UserGameRepository;
use Doctrine\DBAL\Connection;

class RecommendationService
{
    public function __construct(
        private readonly GameRepository $gameRepository,
        private readonly GameSessionRepository $sessionRepository,
        private readonly UserGameRepository $userGameRepository,
        private readonly Connection $conn,
    ) {}

    /**
     * Recommande des jeux depuis la collection de l'utilisateur.
     *
     * Critères de filtrage (durs) :
     *   - collection de l'utilisateur uniquement
     *   - nombre de joueurs (si fourni)
     *   - durée max (si fournie)
     *   - moteurs Engelstein (si fournis)
     *
     * Scoring : personnel > qualité > durée
     */
    public function recommend(User $user, array $criteria): array
    {
        $players    = isset($criteria['players']) && $criteria['players'] > 0
            ? (int) $criteria['players'] : null;
        $maxTime    = isset($criteria['maxTime']) && $criteria['maxTime'] > 0
            ? (int) $criteria['maxTime'] : null;
        $families   = (array) ($criteria['families']   ?? []);
        $categories = (array) ($criteria['categories'] ?? []);

        $games = $this->gameRepository->findUserCollectionForRecommendation(
            $user,
            $players,
            $maxTime,
            $families,
            $categories,
        );

        if (empty($games)) {
            return [];
        }

        // Une seule requête pour toutes les stats de sessions et les notes BGG
        $sessionStats = $this->sessionRepository->getStatsForUser($user);
        $bggUserRatings = $this->userGameRepository->getBggUserRatings($user);

        $now = new \DateTimeImmutable();

        $scored = array_map(function (Game $game) use ($sessionStats, $bggUserRatings, $maxTime, $now) {
            $stats = $sessionStats[$game->getId()] ?? null;
            $bggUserRating = $bggUserRatings[$game->getId()] ?? null;
            $score = $this->computeScore($game, $stats, $bggUserRating, $maxTime, $now);
            return [
                'game'   => $game,
                'score'  => $score,
                'reason' => $this->buildReason($game, $stats, $bggUserRating, $now),
            ];
        }, $games);

        // Exclure les jeux détestés (score très négatif)
        $scored = array_filter($scored, fn($r) => $r['score'] > -50);

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice(array_values($scored), 0, 10);
    }

    /**
     * Recommande des jeux hors collection — découverte.
     * Scoring simplifié : qualité BGG + adéquation durée.
     * Pas d'historique personnel (jamais joué par définition).
     */
    public function recommendDiscover(User $user, array $criteria): array
    {
        $players    = isset($criteria['players']) && $criteria['players'] > 0 ? (int) $criteria['players'] : null;
        $maxTime    = isset($criteria['maxTime']) && $criteria['maxTime'] > 0 ? (int) $criteria['maxTime'] : null;
        $families   = (array) ($criteria['families']   ?? []);
        $categories = (array) ($criteria['categories'] ?? []);

        $games = $this->gameRepository->findDiscoverForRecommendation(
            $user, $players, $maxTime, $families,
            categoryKeys: $categories,
        );

        if (empty($games)) {
            return [];
        }

        $scored = array_map(function (Game $game) use ($maxTime) {
            $score  = 0.0;
            $rating = $game->getRatingBgg() ?? 0;

            if ($rating >= 8.0)      $score += 40;
            elseif ($rating >= 7.5)  $score += 30;
            elseif ($rating >= 7.0)  $score += 20;
            elseif ($rating >= 6.5)  $score += 10;

            if ($maxTime !== null && $game->getPlayingTime() > 0) {
                $ratio = $game->getPlayingTime() / $maxTime;
                if ($ratio <= 0.6)       $score += 10;
                elseif ($ratio <= 0.8)   $score += 7;
                elseif ($ratio <= 1.0)   $score += 3;
            } elseif ($game->getPlayingTime() === 0) {
                $score += 5;
            }

            $reason = $rating >= 7.5
                ? 'Très bien noté sur BGG (' . number_format($rating, 1) . '/10)'
                : 'Noté ' . number_format($rating, 1) . '/10 sur BGG';

            return ['game' => $game, 'score' => $score, 'reason' => $reason];
        }, $games);

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice(array_values($scored), 0, 10);
    }

    /**
     * Recommandations page d'accueil — hors collection, basées sur le profil mécanique de l'utilisateur.
     *
     * Retourne :
     *   - `families`  : les 4 familles Engelstein les plus jouées
     *   - `recent`    : top 10 jeux récents (≥ année courante - 3) correspondant au profil
     *   - `byDecade`  : tableau ['2020s' => [...], '2010s' => [...], ...] top 5 par décennie
     */
    public function recommendHomeDiscover(User $user): array
    {
        // 1. Profil mécanique — jeux notés en priorité, sinon toute la collection
        $topFamilies = $this->getUserTopFamilies($user);

        if (empty($topFamilies)) {
            return ['families' => [], 'recent' => [], 'byDecade' => []];
        }

        $currentYear = (int) date('Y');

        // 2. Jeux récents (3 dernières années)
        $recentGames = $this->gameRepository->findDiscoverForRecommendation(
            $user,
            players:    null,
            maxTime:    null,
            familyKeys: $topFamilies,
            limit:      30,
            minYear:    $currentYear - 3,
        );

        $recent = array_slice(
            $this->scoreGames($recentGames, $topFamilies, recentBoost: true),
            0, 10
        );

        // 3. Par décennie
        $decades = [
            '2020s'    => [2020, $currentYear - 4],   // avant les "récents"
            '2010s'    => [2010, 2019],
            '2000s'    => [2000, 2009],
            '1990s'    => [1990, 1999],
            'Classiques' => [null, 1989],
        ];

        $byDecade = [];
        foreach ($decades as $label => [$min, $max]) {
            // On saute la décennie 2020 si $min > $max (cas où currentYear - 4 < 2020)
            if ($min !== null && $max !== null && $min > $max) {
                continue;
            }

            $games = $this->gameRepository->findDiscoverForRecommendation(
                $user,
                players:    null,
                maxTime:    null,
                familyKeys: $topFamilies,
                limit:      20,
                minYear:    $min,
                maxYear:    $max,
            );

            if (empty($games)) {
                continue;
            }

            $scored = array_slice($this->scoreGames($games, $topFamilies, recentBoost: false), 0, 5);
            if (!empty($scored)) {
                $byDecade[$label] = $scored;
            }
        }

        return [
            'families' => $topFamilies,
            'recent'   => $recent,
            'byDecade' => $byDecade,
        ];
    }

    /** Récupère les 4 familles Engelstein les plus représentées dans les jeux joués/possédés. */
    private function getUserTopFamilies(User $user): array
    {
        // Priorité : jeux notés sur BGG (= joués et appréciés)
        $rows = $this->conn->fetchAllAssociative(
            "SELECT family_key, COUNT(*) AS cnt
             FROM (
                 SELECT json_array_elements_text(g.mechanic_families) AS family_key
                 FROM user_game ug
                 JOIN game g ON g.id = ug.game_id
                 WHERE ug.user_id = ?
                   AND ug.bgg_user_rating IS NOT NULL
                   AND g.mechanic_families::text != '[]'
             ) sub
             GROUP BY family_key
             ORDER BY cnt DESC
             LIMIT 4",
            [$user->getId()]
        );

        $families = array_column($rows, 'family_key');

        // Fallback : toute la collection si aucun jeu noté
        if (empty($families)) {
            $rows = $this->conn->fetchAllAssociative(
                "SELECT family_key, COUNT(*) AS cnt
                 FROM (
                     SELECT json_array_elements_text(g.mechanic_families) AS family_key
                     FROM user_game ug
                     JOIN game g ON g.id = ug.game_id
                     WHERE ug.user_id = ?
                       AND g.mechanic_families::text != '[]'
                 ) sub
                 GROUP BY family_key
                 ORDER BY cnt DESC
                 LIMIT 4",
                [$user->getId()]
            );
            $families = array_column($rows, 'family_key');
        }

        return $families;
    }

    /**
     * Score une liste de jeux selon l'overlap avec le profil + note BGG.
     * @param Game[]  $games
     * @param string[] $topFamilies
     */
    private function scoreGames(array $games, array $topFamilies, bool $recentBoost): array
    {
        $currentYear = (int) date('Y');

        $scored = array_map(function (Game $game) use ($topFamilies, $recentBoost, $currentYear): array {
            $gameFamilies = $game->getMechanicFamilies() ?? [];
            $overlap      = count(array_intersect($topFamilies, $gameFamilies));
            $rating       = $game->getRatingBgg() ?? 0;
            $year         = $game->getYearPublished();

            $score = ($overlap * 15) + ($rating * 3);

            if ($rating >= 8.0)     $score += 20;
            elseif ($rating >= 7.5) $score += 10;

            // Boost récence si demandé
            if ($recentBoost && $year !== null) {
                $age = $currentYear - $year;
                if ($age <= 1)      $score += 25;
                elseif ($age <= 2)  $score += 15;
                elseif ($age <= 3)  $score += 8;
            }

            $reason = match(true) {
                $overlap >= 3 => "Correspond à {$overlap} de vos mécaniques préférées",
                $overlap === 2 => 'Partage 2 de vos mécaniques préférées',
                default        => 'Correspond à votre style de jeu',
            };
            if ($rating >= 7.5) {
                $reason .= ' · ' . number_format($rating, 1) . '/10 sur BGG';
            }
            if ($recentBoost && $year !== null && $year >= $currentYear - 1) {
                $reason = '🆕 Nouveauté ' . $year . ' · ' . $reason;
            }

            return ['game' => $game, 'score' => $score, 'reason' => $reason];
        }, $games);

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_values($scored);
    }

    public function findForgottenGems(User $user): array
    {
        $games = $this->gameRepository->findUserCollectionForRecommendation($user, null, null);

        if (empty($games)) {
            return [];
        }

        $sessionStats = $this->sessionRepository->getStatsForUser($user);
        $now = new \DateTimeImmutable();
        $threshold = $now->modify('-180 days');

        $gems = [];
        foreach ($games as $game) {
            $stats = $sessionStats[$game->getId()] ?? null;
            $lastPlayed = $stats['lastPlayedAt'] ?? null;

            $isOldOrNeverPlayed = $lastPlayed === null || $lastPlayed < $threshold;
            $isNotDisliked = ($stats['worstRating'] ?? null) !== 1;
            $hasGoodRating = ($game->getRatingBgg() ?? 0) >= 7.0;

            if ($isOldOrNeverPlayed && $isNotDisliked && $hasGoodRating) {
                $gems[] = [
                    'game'   => $game,
                    'reason' => $this->buildForgottenReason($game, $stats),
                ];
            }
        }

        usort($gems, fn($a, $b) =>
            ($b['game']->getRatingBgg() ?? 0) <=> ($a['game']->getRatingBgg() ?? 0)
        );

        return array_slice($gems, 0, 10);
    }

    private function computeScore(Game $game, ?array $stats, ?float $bggUserRating, ?int $maxTime, \DateTimeImmutable $now): float
    {
        $score = 0.0;

        // --- Score personnel (signal le plus fort) ---
        $score += $this->personalScore($stats, $bggUserRating, $now);

        // --- Qualité BGG (tiebreaker, pas dominant) ---
        $rating = $game->getRatingBgg() ?? 0;
        if ($rating >= 8.0) {
            $score += 20;
        } elseif ($rating >= 7.0) {
            $score += 12;
        } elseif ($rating >= 6.0) {
            $score += 5;
        }

        // --- Adéquation durée (gradient) ---
        if ($maxTime !== null && $game->getPlayingTime() > 0) {
            $ratio = $game->getPlayingTime() / $maxTime;
            if ($ratio <= 0.6) {
                $score += 10;
            } elseif ($ratio <= 0.8) {
                $score += 7;
            } elseif ($ratio <= 1.0) {
                $score += 3;
            }
            // Au-delà de max → déjà exclu par le filtre
        } elseif ($game->getPlayingTime() === 0) {
            $score += 5; // durée inconnue, neutre légèrement positif
        }

        return $score;
    }

    private function personalScore(?array $stats, ?float $bggUserRating, \DateTimeImmutable $now): float
    {
        // Note personnelle BGG : signal le plus fort (1-10)
        if ($bggUserRating !== null) {
            if ($bggUserRating < 5) {
                return -50.0; // Pas aimé — écarté
            }
            $score = match(true) {
                $bggUserRating >= 9.0 => 35.0,
                $bggUserRating >= 8.0 => 25.0,
                $bggUserRating >= 7.0 => 15.0,
                $bggUserRating >= 6.0 => 5.0,
                default               => -5.0,
            };

            // Bonus fraîcheur même sur jeux notés
            if ($stats !== null) {
                $lastPlayed = $stats['lastPlayedAt'];
                if ($lastPlayed !== null) {
                    $daysSince = (int) $now->diff($lastPlayed)->days;
                    if ($daysSince >= 365) {
                        $score += 20;
                    } elseif ($daysSince < 7) {
                        $score -= 20;
                    } elseif ($daysSince < 30) {
                        $score -= 10;
                    }
                }
            }

            return $score;
        }

        // Pas de note BGG — on se base sur les sessions locales
        if ($stats === null) {
            // Jamais joué — découverte
            return 15.0;
        }

        $score = 0.0;

        // Avis de l'utilisateur (sessions locales)
        if (($stats['worstRating'] ?? null) === 1) {
            return -100.0;
        }
        if (($stats['bestRating'] ?? null) === 5) {
            $score += 30;
        }

        // Fraîcheur
        $lastPlayed = $stats['lastPlayedAt'];
        if ($lastPlayed !== null) {
            $daysSince = (int) $now->diff($lastPlayed)->days;

            if ($daysSince >= 365) {
                $score += 20;
            } elseif ($daysSince >= 180) {
                $score += 10;
            } elseif ($daysSince < 7) {
                $score -= 25;
            } elseif ($daysSince < 30) {
                $score -= 15;
            }
        }

        return $score;
    }

    private function buildReason(Game $game, ?array $stats, ?float $bggUserRating, \DateTimeImmutable $now): string
    {
        $parts = [];

        if ($bggUserRating !== null) {
            $parts[] = "vous avez noté {$bggUserRating}/10 sur BGG";
        } elseif ($stats === null) {
            $parts[] = 'jamais joué — à découvrir';
        } elseif (($stats['worstRating'] ?? null) !== 1) {
            if (($stats['bestRating'] ?? null) === 5) {
                $parts[] = 'vous avez aimé ce jeu';
            }
            if (($stats['sessionCount'] ?? 0) > 0) {
                $count = $stats['sessionCount'];
                $parts[] = "joué {$count} fois";
            }
        }

        if ($stats !== null) {
            $lastPlayed = $stats['lastPlayedAt'];
            if ($lastPlayed !== null) {
                $daysSince = (int) $now->diff($lastPlayed)->days;
                if ($daysSince >= 365) {
                    $parts[] = 'pas joué depuis plus d\'un an';
                } elseif ($daysSince >= 180) {
                    $parts[] = 'pas joué depuis 6+ mois';
                }
            }
        }

        if ($game->getRatingBgg() !== null && $game->getRatingBgg() >= 7.5) {
            $rating = number_format($game->getRatingBgg(), 1, '.', '');
            $parts[] = "noté {$rating}/10 sur BGG";
        }

        if (empty($parts)) {
            $parts[] = 'jeu de votre collection';
        }

        return ucfirst(implode(' · ', $parts));
    }

    private function buildForgottenReason(Game $game, ?array $stats): string
    {
        if ($stats === null) {
            return 'Jamais joué, mais dans votre collection';
        }

        $lastPlayed = $stats['lastPlayedAt'];
        if ($lastPlayed !== null) {
            $months = (int) (new \DateTimeImmutable())->diff($lastPlayed)->days / 30;
            return "Pas joué depuis {$months} mois";
        }

        return 'Jeu oublié de votre collection';
    }
}
