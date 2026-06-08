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

        return array_slice(array_values($scored), 0, 30);
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
            limit: 100,
            categoryKeys: $categories,
        );

        if (empty($games)) {
            return [];
        }

        $scored = array_map(function (Game $game) use ($maxTime) {
            $score  = 0.0;
            $rating = $this->bayesianRating($game->getRatingBgg(), $game->getUsersRated());

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

            $rawRating = $game->getRatingBgg() ?? 0;
            $reason = $rawRating >= 7.5
                ? 'Très bien noté sur BGG (' . number_format($rawRating, 1) . '/10)'
                : 'Noté ' . number_format($rawRating, 1) . '/10 sur BGG';

            return ['game' => $game, 'score' => $score, 'reason' => $reason];
        }, $games);

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice(array_values($scored), 0, 30);
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

        // 2. Jeux à exclure (détestés)
        $excludeIds = $this->getDislikedGameIds($user);

        // 3. Jeux récents (3 dernières années)
        $recentGames = $this->gameRepository->findDiscoverForRecommendation(
            $user,
            players:    null,
            maxTime:    null,
            familyKeys: $topFamilies,
            limit:      30,
            minYear:    $currentYear - 3,
            excludeIds: $excludeIds,
        );

        $recent = array_slice(
            $this->scoreGames($recentGames, $topFamilies, recentBoost: true),
            0, 10
        );

        // 4. Par décennie
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
                excludeIds: $excludeIds,
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

    /** Récupère les 4 familles Engelstein les plus représentées dans les jeux aimés. */
    private function getUserTopFamilies(User $user): array
    {
        // Priorité 1 : jeux bien notés sur BGG (>= 6) — signal fort "j'aime"
        $rows = $this->conn->fetchAllAssociative(
            "SELECT family_key, COUNT(*) AS cnt
             FROM (
                 SELECT json_array_elements_text(g.mechanic_families) AS family_key
                 FROM user_game ug
                 JOIN game g ON g.id = ug.game_id
                 WHERE ug.user_id = ?
                   AND ug.bgg_user_rating >= 6
                   AND g.mechanic_families::text != '[]'
             ) sub
             GROUP BY family_key
             ORDER BY cnt DESC
             LIMIT 4",
            [$user->getId()]
        );

        $families = array_column($rows, 'family_key');

        // Priorité 2 : parties aimées (rating = 5) — si pas assez de notes BGG
        if (empty($families)) {
            $rows = $this->conn->fetchAllAssociative(
                "SELECT family_key, COUNT(*) AS cnt
                 FROM (
                     SELECT json_array_elements_text(g.mechanic_families) AS family_key
                     FROM game_session gs
                     JOIN game g ON g.id = gs.game_id
                     WHERE gs.user_id = ?
                       AND gs.rating = 5
                       AND g.mechanic_families::text != '[]'
                 ) sub
                 GROUP BY family_key
                 ORDER BY cnt DESC
                 LIMIT 4",
                [$user->getId()]
            );
            $families = array_column($rows, 'family_key');
        }

        // Fallback 3 : collection entière (hors jeux explicitement détestés)
        if (empty($families)) {
            $rows = $this->conn->fetchAllAssociative(
                "SELECT family_key, COUNT(*) AS cnt
                 FROM (
                     SELECT json_array_elements_text(g.mechanic_families) AS family_key
                     FROM user_game ug
                     JOIN game g ON g.id = ug.game_id
                     WHERE ug.user_id = ?
                       AND (ug.bgg_user_rating IS NULL OR ug.bgg_user_rating >= 5)
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

    /** IDs des jeux explicitement détestés (note BGG < 5, ou session avec rating = 1). */
    private function getDislikedGameIds(User $user): array
    {
        $rows = $this->conn->fetchFirstColumn(
            "SELECT DISTINCT game_id FROM (
                 SELECT ug.game_id FROM user_game ug
                 WHERE ug.user_id = ? AND ug.bgg_user_rating < 5
                 UNION
                 SELECT gs.game_id FROM game_session gs
                 WHERE gs.user_id = ? AND gs.rating = 1
             ) sub",
            [$user->getId(), $user->getId()]
        );

        return array_map('intval', $rows);
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
            $rating       = $this->bayesianRating($game->getRatingBgg(), $game->getUsersRated());
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

    /**
     * Calcule un score de similarité entre deux jeux (0-100).
     *
     * Pondération :
     *   mécaniques Engelstein  40 %
     *   catégories BGG          20 %
     *   plage de joueurs        15 %
     *   complexité              15 %
     *   âge minimum             10 %
     */
    public function computeSimilarity(Game $ref, Game $candidate): int
    {
        // ── 1. Mécaniques Engelstein (40 %) ──────────────────────────────
        $refFamilies = $ref->getMechanicFamilies();
        $canFamilies = $candidate->getMechanicFamilies();
        $union        = array_unique(array_merge($refFamilies, $canFamilies));
        $mechScore    = count($union) > 0
            ? count(array_intersect($refFamilies, $canFamilies)) / count($union)
            : 0.0;

        // ── 2. Catégories BGG (thèmes + types) (20 %) ───────────────────
        $refCats = $ref->getCategories();
        $canCats = $candidate->getCategories();
        $catUnion = array_unique(array_merge($refCats, $canCats));
        $catScore = count($catUnion) > 0
            ? count(array_intersect($refCats, $canCats)) / count($catUnion)
            : 0.0;

        // ── 3. Plage de joueurs (15 %) ────────────────────────────────────
        // Recouvrement des intervalles [min, max]
        $overlapMin = max($ref->getMinPlayers(),  $candidate->getMinPlayers());
        $overlapMax = min($ref->getMaxPlayers(),  $candidate->getMaxPlayers());
        $unionMin   = min($ref->getMinPlayers(),  $candidate->getMinPlayers());
        $unionMax   = max($ref->getMaxPlayers(),  $candidate->getMaxPlayers());
        $playerScore = ($unionMax - $unionMin) > 0 && $overlapMax >= $overlapMin
            ? ($overlapMax - $overlapMin + 1) / ($unionMax - $unionMin + 1)
            : ($overlapMax >= $overlapMin ? 1.0 : 0.0);

        // ── 4. Complexité (15 %) ─────────────────────────────────────────
        $refComp  = $ref->getComplexity();
        $canComp  = $candidate->getComplexity();
        $complexScore = ($refComp > 0 && $canComp > 0)
            ? max(0.0, 1.0 - abs($refComp - $canComp) / 4.0)   // échelle 1-5
            : 0.5; // inconnue → neutre

        // ── 5. Âge minimum (10 %) ────────────────────────────────────────
        $refAge  = $ref->getMinAge();
        $canAge  = $candidate->getMinAge();
        $ageScore = ($refAge !== null && $canAge !== null)
            ? max(0.0, 1.0 - abs($refAge - $canAge) / 10.0)
            : 0.5;

        $total = $mechScore * 0.40
               + $catScore  * 0.20
               + $playerScore * 0.15
               + $complexScore * 0.15
               + $ageScore  * 0.10;

        return (int) round($total * 100);
    }

    /**
     * Trouve les N jeux les plus similaires à un jeu donné (hors expansions).
     * Tous jeux confondus, peu importe si l'utilisateur les possède ou non.
     *
     * @return array<array{game: Game, similarity: int, reason: string}>
     */
    public function findSimilar(Game $ref, int $limit = 5): array
    {
        $candidates = $this->gameRepository->findSimilarCandidates($ref, $limit * 6);

        $scored = array_map(function (Game $candidate) use ($ref): array {
            $sim    = $this->computeSimilarity($ref, $candidate);
            $rating = $this->bayesianRating($candidate->getRatingBgg(), $candidate->getUsersRated());

            // Boost note pour départager les ex-aequo
            $score  = $sim * 100 + $rating * 0.5;

            $reason = $this->buildSimilarReason($ref, $candidate, $sim);

            return ['game' => $candidate, 'similarity' => $sim, 'reason' => $reason, '_score' => $score];
        }, $candidates);

        usort($scored, fn($a, $b) => $b['_score'] <=> $a['_score']);

        return array_slice(
            array_map(fn($r) => ['game' => $r['game'], 'similarity' => $r['similarity'], 'reason' => $r['reason']], $scored),
            0, $limit
        );
    }

    private function buildSimilarReason(Game $ref, Game $candidate, int $sim): string
    {
        $sharedFamilies = array_intersect($ref->getMechanicFamilies(), $candidate->getMechanicFamilies());
        $sharedCats     = array_intersect($ref->getCategories(), $candidate->getCategories());

        $parts = [];
        if (!empty($sharedFamilies)) {
            $labels = array_map(fn($f) => $f, array_slice(array_values($sharedFamilies), 0, 2));
            $parts[] = 'mécaniques similaires';
        }
        if (!empty($sharedCats)) {
            $parts[] = 'même univers';
        }
        if ($candidate->getRatingBgg() !== null && $candidate->getRatingBgg() >= 7.5) {
            $parts[] = number_format($candidate->getRatingBgg(), 1) . '/10 BGG';
        }

        $prefix = $sim >= 80 ? 'Très proche' : ($sim >= 60 ? 'Proche' : 'Dans le même style');
        return $prefix . ($parts ? ' · ' . implode(', ', $parts) : '');
    }

    /**
     * Note BGG ajustée bayésienne — pénalise les jeux avec peu de votants.
     *
     * Formule : (votes * avg + C * mean) / (votes + C)
     *   C    = 500  (seuil de confiance)
     *   mean = 6.8  (moyenne globale estimée des jeux BGG enrichis)
     */
    private function bayesianRating(?float $ratingBgg, ?int $usersRated): float
    {
        if ($ratingBgg === null) {
            return 0.0;
        }
        $votes = $usersRated ?? 0;
        $C     = 500;
        $mean  = 6.8;
        return ($votes * $ratingBgg + $C * $mean) / ($votes + $C);
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
