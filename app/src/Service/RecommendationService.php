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
    /** Fenêtre « jeux récents » : l'année courante et les quatre précédentes. Valeur unique pour tout le service. */
    private const RECENT_YEARS = 5;

    /** Plus petit nombre de votes qu'une nouveauté doit avoir pour être proposée. */
    private const RECENT_MIN_VOTES = 100;

    /** Points de score ajoutés à un jeu paru cette année ; décroît linéairement jusqu'à 5 ans. */
    private const RECENT_MAX_BONUS = 25;

    /** Complexité BGG (1-5) au plus : un jeu qu'on explique en cinq minutes autour d'une table. */
    private const EASY_MAX_COMPLEXITY = 2.5;

    /** Un jeu n'est « joué par les autres membres » qu'à partir de ce nombre de joueurs distincts : jamais le choix d'une seule personne. */
    private const POPULAR_MIN_PLAYERS = 2;

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

        return array_slice($scored, 0, 30);
    }

    /**
     * Recommande des jeux hors collection — découverte.
     * Avec `$library`, ne propose que les jeux de la ludothèque de l'association et des membres (collection du joueur comprise).
     * Scoring simplifié : qualité BGG + adéquation durée.
     * Pas d'historique personnel (jamais joué par définition).
     */
    public function recommendDiscover(User $user, array $criteria, bool $library = false): array
    {
        $players    = isset($criteria['players']) && $criteria['players'] > 0 ? (int) $criteria['players'] : null;
        $maxTime    = isset($criteria['maxTime']) && $criteria['maxTime'] > 0 ? (int) $criteria['maxTime'] : null;
        $families   = (array) ($criteria['families']   ?? []);
        $categories = (array) ($criteria['categories'] ?? []);

        $currentYear = (int) date('Y');

        // Triée par note brute, la réserve générale ne contient presque que des classiques : on y ajoute
        // un second lot de jeux récents, sinon aucune nouveauté n'atteindrait jamais le classement.
        $general = $this->gameRepository->findDiscoverForRecommendation(
            $user, $players, $maxTime, $families,
            limit: 100,
            categoryKeys: $categories,
            library: $library,
        );
        $recentPool = $this->gameRepository->findDiscoverForRecommendation(
            $user, $players, $maxTime, $families,
            limit: 60,
            minYear: $this->recentFromYear($currentYear),
            categoryKeys: $categories,
            minVotes: self::RECENT_MIN_VOTES,
            library: $library,
        );

        $games = [];
        foreach ([...$general, ...$recentPool] as $game) {
            $games[$game->getId()] = $game;
        }

        if (empty($games)) {
            return [];
        }

        $scored = array_map(function (Game $game) use ($maxTime, $currentYear) {
            $score  = $this->recencyBonus($game, $currentYear);
            $rating = $this->qualityRating($game, $currentYear);

            // Qualité continue : 0 point à 6,5, 40 points dès 8,0 (plus de palier qui départage deux jeux à 0,01 près).
            $score += max(0.0, min(40.0, ($rating - 6.5) * 40 / 1.5));

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
            if ($this->isRecent($game, $currentYear)) {
                $reason = 'Nouveauté ' . $game->getYearPublished() . ' · ' . $reason;
            }

            return ['game' => $game, 'score' => $score, 'reason' => $reason];
        }, $games);

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, 30);
    }

    /**
     * Recommandations page d'accueil — hors collection, basées sur le profil mécanique de l'utilisateur.
     *
     * Retourne :
     *   - `families`  : les 4 familles Engelstein les plus jouées
     *   - `recent`    : top 10 jeux récents (≥ année courante - 3) correspondant au profil
     *   - `byDecade`  : tableau ['2020s' => [...], '2010s' => [...], ...] top 5 par décennie
     *   - `coldStart` : true quand ni l'historique ni le questionnaire ne donnent de profil ;
     *                   `recent` propose alors des nouveautés accessibles, sans filtre de mécanique
     *   - `declared`  : goûts déclarés par le joueur (pour préremplir le questionnaire)
     */
    public function recommendHomeDiscover(User $user, bool $library = false): array
    {
        // 1. Profil mécanique — jeux notés en priorité, sinon toute la collection
        $declared    = $this->getDeclaredFamilies($user);
        $topFamilies = $this->getUserTopFamilies($user, $declared);
        $currentYear = (int) date('Y');

        // 2. Jeux à exclure (détestés)
        $excludeIds = $this->getDislikedGameIds($user);

        if (empty($topFamilies)) {
            return [
                'families'  => [],
                'recent'    => $this->newReleases($user),
                'byDecade'  => [],
                'coldStart' => true,
                'declared'  => $declared,
            ];
        }

        // 3. Nouveautés BGG, hors ludothèque du joueur
        $recent = $this->newReleases($user);

        // 4. Par décennie
        $decades = [
            '2020s'    => [2020, null],
            '2010s'    => [2010, 2019],
            '2000s'    => [2000, 2009],
            '1990s'    => [1990, 1999],
            'Classiques' => [null, 1989],
        ];

        $byDecade = [];
        foreach ($decades as $label => [$min, $max]) {
            // On saute la décennie 2020 si $min > $max (cas où la fenêtre des récents couvre déjà tout le début des années 2020)
            if ($max !== null && $min !== null && $min > $max) {
                continue;
            }

            $games = $this->gameRepository->findDiscoverForRecommendation(
                $user,
                players:    null,
                maxTime:    null,
                familyKeys: $library ? [] : $topFamilies,
                limit:      20,
                minYear:    $min,
                maxYear:    $max,
                excludeIds: $excludeIds,
                baseGamesOnly: true,
                library:    $library,
            );

            if (empty($games)) {
                continue;
            }

            $scored = $this->pickRandom($this->scoreGames($games, $topFamilies, recentBoost: false), 15, 5);
            if (!empty($scored)) {
                $byDecade[$label] = $scored;
            }
        }

        return [
            'families'  => $topFamilies,
            'recent'    => $recent,
            'byDecade'  => $byDecade,
            'coldStart' => false,
            'declared'  => $declared,
        ];
    }

    /**
     * Tire `$count` éléments au hasard parmi les `$pool` premiers d'une liste déjà classée : la page d'accueil
     * propose d'autres jeux à chaque chargement sans descendre dans les mal classés.
     *
     * @template T
     * @param list<T> $ranked
     * @return list<T>
     */
    private function pickRandom(array $ranked, int $pool, int $count): array
    {
        $candidates = array_slice(array_values($ranked), 0, $pool);
        shuffle($candidates);

        return array_slice($candidates, 0, $count);
    }

    /**
     * Une sélection de 10 nouveautés BGG, parmi les 30 plus suivies : jeux de base parus récemment ou à paraître, les plus suivis par les votants,
     * que le joueur n'a pas déjà. Indépendant des goûts et du périmètre (ludothèque ou catalogue) : c'est ce qui se passe sur BGG.
     *
     * Dix jeux tirés au hasard parmi les trente plus votés.
     *
     * « Récemment » = l'année en cours (et la précédente jusqu'en juin, pour ne pas vider la liste en janvier) ;
     * « à paraître » = jusqu'à l'année prochaine incluse. Classement par nombre de votes, la mesure d'intérêt que
     * le catalogue conserve ; un jeu à moins de 6/10 de moyenne est écarté.
     *
     * @return array<array{game: Game, score: float, reason: string}>
     */
    private function newReleases(User $user): array
    {
        $currentYear = (int) date('Y');
        $fromYear    = (int) date('n') <= 6 ? $currentYear - 1 : $currentYear;
        // Les 30 plus suivies, dont 10 au hasard : d'autres nouveautés à chaque chargement.
        $games = $this->pickRandom($this->gameRepository->findNewReleases($user, $fromYear, $currentYear + 1, 30), 30, 10);

        return array_map(function (Game $game) use ($currentYear): array {
            $year   = $game->getYearPublished();
            $reason = $year !== null && $year > $currentYear ? "À paraître en {$year}" : "Sorti en {$year}";
            if (($game->getUsersRated() ?? 0) > 0) {
                $reason .= ' · ' . number_format($game->getUsersRated(), 0, ',', "\u{202F}") . ' votes';
            }
            if ($game->getRatingBgg() !== null) {
                $reason .= ' · ' . number_format($game->getRatingBgg(), 1) . '/10 sur BGG';
            }

            return ['game' => $game, 'score' => (float) ($game->getUsersRated() ?? 0), 'reason' => $reason];
        }, $games);
    }

    /** Goûts que le joueur a déclarés lui-même (questionnaire de l'accueil). */
    private function getDeclaredFamilies(User $user): array
    {
        $raw = $this->conn->fetchOne('SELECT declared_families FROM user_preference WHERE user_id = ?', [$user->getId()]);
        $families = $raw === false ? [] : json_decode((string) $raw, true);

        return is_array($families) ? array_values(array_filter($families, 'is_string')) : [];
    }

    /**
     * Idées pour la table du soir (nombre de joueurs et durée choisis sur l'accueil).
     *
     *   - `easy`    : jeux de base abordables du catalogue, nouveautés favorisées
     *   - `popular` : ce que les autres membres ont joué dans l'année (au moins deux joueurs distincts)
     *
     * @return array{easy: array, popular: array}
     */
    public function recommendTonight(User $user, ?int $players, ?int $maxTime, bool $library = false): array
    {
        $currentYear = (int) date('Y');
        $excludeIds  = $this->getDislikedGameIds($user);

        $pool = [];
        foreach ([
            ['limit' => 60, 'minYear' => null, 'minVotes' => 300],
            ['limit' => 40, 'minYear' => $this->recentFromYear($currentYear), 'minVotes' => self::RECENT_MIN_VOTES],
        ] as $lot) {
            $games = $this->gameRepository->findDiscoverForRecommendation(
                $user, $players, $maxTime,
                limit:         $lot['limit'],
                minYear:       $lot['minYear'],
                excludeIds:    $excludeIds,
                baseGamesOnly: true,
                maxComplexity: self::EASY_MAX_COMPLEXITY,
                minVotes:      $lot['minVotes'],
                library:       $library,
            );
            foreach ($games as $game) {
                $pool[$game->getId()] = $game;
            }
        }

        $easy = array_map(function (Game $game) use ($currentYear): array {
            $reason = 'Facile à aborder · ' . number_format((float) $game->getRatingBgg(), 1) . '/10 sur BGG';
            if ($this->isRecent($game, $currentYear)) {
                $reason = 'Nouveauté ' . $game->getYearPublished() . ' · ' . $reason;
            }

            return [
                'game'   => $game,
                'score'  => $this->qualityRating($game, $currentYear) * 5 + $this->recencyBonus($game, $currentYear),
                'reason' => $reason,
            ];
        }, array_values($pool));
        usort($easy, fn($a, $b) => $b['score'] <=> $a['score']);

        return [
            'easy'    => $this->pickRandom($easy, 30, 10),
            'popular' => $this->popularAmongMembers($user, $players, $maxTime, $excludeIds, $library),
        ];
    }

    /**
     * Jeux de base les plus joués par les autres membres sur douze mois. Seuls des totaux sortent :
     * jamais qui a joué, et rien tant que deux joueurs distincts ne l'ont pas joué.
     */
    private function popularAmongMembers(User $user, ?int $players, ?int $maxTime, array $excludeIds, bool $library): array
    {
        $conditions = [
            'gs.user_id IS NOT NULL',
            'gs.user_id <> ?',
            "gs.played_at >= NOW() - INTERVAL '365 days'",
            'g.is_expansion = false',
        ];
        $params = [$user->getId()];

        if ($library) {
            $conditions[] = GameRepository::IN_LIBRARY;
        }
        if ($players !== null) {
            $conditions[] = 'g.min_players <= ? AND g.max_players >= ?';
            array_push($params, $players, $players);
        }
        if ($maxTime !== null) {
            $conditions[] = '(g.playing_time <= ? OR g.playing_time = 0)';
            $params[] = $maxTime;
        }
        if ($excludeIds !== []) {
            $conditions[] = 'g.id NOT IN (' . implode(',', array_fill(0, count($excludeIds), '?')) . ')';
            array_push($params, ...$excludeIds);
        }
        array_push($params, self::POPULAR_MIN_PLAYERS);

        $rows = $this->conn->fetchAllAssociative(
            'SELECT gs.game_id, COUNT(DISTINCT gs.user_id) AS players, COUNT(*) AS sessions
             FROM game_session gs
             JOIN game g ON g.id = gs.game_id
             WHERE ' . implode(' AND ', $conditions) . '
             GROUP BY gs.game_id
             HAVING COUNT(DISTINCT gs.user_id) >= ?
             ORDER BY players DESC, sessions DESC, gs.game_id
             LIMIT 20',
            $params
        );
        if ($rows === []) {
            return [];
        }

        $games = [];
        foreach ($this->gameRepository->findBy(['id' => array_column($rows, 'game_id')]) as $game) {
            $games[$game->getId()] = $game;
        }

        $popular = [];
        foreach ($rows as $row) {
            $game = $games[(int) $row['game_id']] ?? null;
            if ($game !== null) {
                $popular[] = ['game' => $game, 'reason' => 'Joué par ' . $row['players'] . ' membres cette année'];
            }
        }

        return $this->pickRandom($popular, 20, 10);
    }

    /** Les 4 familles Engelstein du profil : goûts déclarés d'abord, puis celles des jeux aimés. */
    private function getUserTopFamilies(User $user, array $declared = []): array
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

        // Les goûts déclarés passent devant : un choix explicite vaut mieux qu'une déduction, et l'historique
        // complète ce que le joueur n'a pas dit (jusqu'à quatre familles).
        $families = array_slice(array_values(array_unique([...$declared, ...$families])), 0, 4);

        // Fallback : collection entière (hors jeux explicitement détestés)
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
            $gameFamilies = $game->getMechanicFamilies();
            $overlap      = count(array_intersect($topFamilies, $gameFamilies));
            $rating       = $this->qualityRating($game, $currentYear);
            $year         = $game->getYearPublished();

            $score = ($overlap * 15) + ($rating * 3);

            if ($rating >= 8.0)     $score += 20;
            elseif ($rating >= 7.5) $score += 10;

            // Bonus de récence continu, réclamé seulement pour la liste des nouveautés
            if ($recentBoost) {
                $score += $this->recencyBonus($game, $currentYear);
            }

            $reason = match(true) {
                $overlap >= 3 => "Correspond à {$overlap} de vos mécaniques préférées",
                $overlap === 2 => 'Partage 2 de vos mécaniques préférées',
                default        => 'Correspond à votre style de jeu',
            };
            if ($rating >= 7.5) {
                $reason .= ' · ' . number_format($rating, 1) . '/10 sur BGG';
            }
            if ($recentBoost && $this->isRecent($game, $currentYear)) {
                $reason = 'Nouveauté ' . $year . ' · ' . $reason;
            }

            return ['game' => $game, 'score' => $score, 'reason' => $reason];
        }, $games);

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return $scored;
    }

    /**
     * Ressemblance de deux jeux, de 0 à 100.
     *
     * Pondération (une mesure sans donnée des deux côtés est écartée et les autres sont rééchelonnées,
     * au lieu de valoir un « neutre » qui gonflait le score des fiches pauvres) :
     *   familles de mécaniques   30 %  Jaccard pondéré : un moteur Engelstein compte triple
     *   mécaniques BGG brutes    15 %  Jaccard
     *   catégories BGG           20 %  Jaccard
     *   complexité               15 %  écart de 2,5 points = rien en commun
     *   joueurs                  10 %  recouvrement des plages
     *   durée                    10 %  rapport entre les deux durées
     */
    public function computeSimilarity(Game $ref, Game $candidate): int
    {
        $parts = [];

        $engines = array_unique([...$ref->getDetectedEngines(), ...$candidate->getDetectedEngines()]);
        $weight  = fn(string $family): int => in_array($family, $engines, true) ? 3 : 1;
        $parts[] = [30, $this->weightedJaccard($ref->getMechanicFamilies(), $candidate->getMechanicFamilies(), $weight)];
        $parts[] = [15, $this->weightedJaccard($ref->getMechanics(), $candidate->getMechanics(), fn() => 1)];
        $parts[] = [20, $this->weightedJaccard($ref->getCategories(), $candidate->getCategories(), fn() => 1)];

        $refComp = $ref->getComplexity();
        $canComp = $candidate->getComplexity();
        $parts[] = [15, ($refComp > 0 && $canComp > 0) ? max(0.0, 1.0 - abs($refComp - $canComp) / 2.5) : null];

        $overlapMin = max($ref->getMinPlayers(), $candidate->getMinPlayers());
        $overlapMax = min($ref->getMaxPlayers(), $candidate->getMaxPlayers());
        $unionMin   = min($ref->getMinPlayers(), $candidate->getMinPlayers());
        $unionMax   = max($ref->getMaxPlayers(), $candidate->getMaxPlayers());
        $parts[] = [10, $overlapMax >= $overlapMin ? ($overlapMax - $overlapMin + 1) / ($unionMax - $unionMin + 1) : 0.0];

        $refTime = $ref->getPlayingTime();
        $canTime = $candidate->getPlayingTime();
        $parts[] = [10, ($refTime > 0 && $canTime > 0) ? min($refTime, $canTime) / max($refTime, $canTime) : null];

        $weights = 0;
        $total   = 0.0;
        foreach ($parts as [$w, $score]) {
            if ($score !== null) {
                $weights += $w;
                $total   += $w * $score;
            }
        }

        return $weights > 0 ? (int) round($total / $weights * 100) : 0;
    }

    /**
     * Jaccard où chaque élément pèse `$weight(élément)`. Null quand les deux listes sont vides :
     * aucune information n'est une absence de mesure, pas une ressemblance nulle.
     *
     * @param list<string>          $a
     * @param list<string>          $b
     * @param callable(string): int $weight
     */
    private function weightedJaccard(array $a, array $b, callable $weight): ?float
    {
        $union = array_unique([...$a, ...$b]);
        if ($union === []) {
            return null;
        }
        $common = array_intersect($a, $b);

        return array_sum(array_map($weight, $common)) / array_sum(array_map($weight, $union));
    }

    /**
     * Trouve les N jeux les plus similaires à un jeu donné (hors expansions).
     * Tous jeux confondus, peu importe si l'utilisateur les possède ou non.
     *
     * @return array<array{game: Game, similarity: int, reason: string}>
     */
    public function findSimilar(Game $ref, int $limit = 5): array
    {
        $candidates = $this->gameRepository->findSimilarCandidates($ref, max(120, $limit * 12));

        $scored = array_map(function (Game $candidate) use ($ref): array {
            $sim    = $this->computeSimilarity($ref, $candidate);
            $rating = $this->bayesianRating($candidate->getRatingBgg(), $candidate->getUsersRated());

            // Boost note pour départager les ex-aequo
            $score  = $sim * 100 + $rating * 0.5;

            $reason = $this->buildSimilarReason($ref, $candidate, $sim);

            return ['game' => $candidate, 'similarity' => $sim, 'reason' => $reason, '_score' => $score];
        }, $candidates);

        // En dessous de 25 %, ce n'est plus « similaire » : mieux vaut en montrer moins.
        $scored = array_filter($scored, fn($r) => $r['similarity'] >= 25);
        usort($scored, fn($a, $b) => $b['_score'] <=> $a['_score']);

        return array_slice(
            array_map(fn($r) => ['game' => $r['game'], 'similarity' => $r['similarity'], 'reason' => $r['reason']], $scored),
            0, $limit
        );
    }

    private function buildSimilarReason(Game $ref, Game $candidate, int $sim): string
    {
        // Ce qu'ils ont concrètement en commun : mécaniques BGG d'abord, sinon catégories.
        $shared = array_values(array_intersect($ref->getMechanics(), $candidate->getMechanics()));
        if ($shared === []) {
            $shared = array_values(array_intersect($ref->getCategories(), $candidate->getCategories()));
        }

        $parts = [];
        if ($shared !== []) {
            $parts[] = 'en commun : ' . implode(', ', array_slice($shared, 0, 2));
        }
        if ($candidate->getRatingBgg() !== null && $candidate->getRatingBgg() >= 7.5 && ($candidate->getUsersRated() ?? 0) >= 100) {
            $parts[] = number_format($candidate->getRatingBgg(), 1) . '/10 BGG';
        }

        $prefix = $sim >= 75 ? 'Très proche' : ($sim >= 55 ? 'Proche' : 'Dans le même style');

        return $prefix . ($parts ? ' · ' . implode(' · ', $parts) : '');
    }

    private function recentFromYear(int $currentYear): int
    {
        return $currentYear - self::RECENT_YEARS + 1;
    }

    private function isRecent(Game $game, int $currentYear): bool
    {
        $year = $game->getYearPublished();

        return $year !== null && $year >= $this->recentFromYear($currentYear);
    }

    /** De RECENT_MAX_BONUS (paru cette année) à 0 (hors de la fenêtre), sans palier. */
    private function recencyBonus(Game $game, int $currentYear): float
    {
        $year = $game->getYearPublished();
        if ($year === null) {
            return 0.0;
        }
        $age = max(0, $currentYear - $year);

        return max(0.0, self::RECENT_MAX_BONUS * (1 - $age / self::RECENT_YEARS));
    }

    /**
     * Note servant au classement. Une nouveauté n'a pas eu le temps d'accumuler des votes : lui appliquer le
     * même seuil de confiance qu'un classique la tire vers la moyenne, donc on le réduit pour elle.
     */
    private function qualityRating(Game $game, int $currentYear): float
    {
        return $this->bayesianRating(
            $game->getRatingBgg(),
            $game->getUsersRated(),
            $this->isRecent($game, $currentYear) ? 150 : 500,
        );
    }

    /**
     * Note BGG ajustée bayésienne — pénalise les jeux avec peu de votants.
     *
     * Formule : (votes * avg + C * mean) / (votes + C)
     *   C    = 500  (seuil de confiance ; 150 pour une nouveauté, voir qualityRating)
     *   mean = 6.8  (moyenne globale estimée des jeux BGG enrichis)
     */
    private function bayesianRating(?float $ratingBgg, ?int $usersRated, int $C = 500): float
    {
        if ($ratingBgg === null) {
            return 0.0;
        }
        $votes = $usersRated ?? 0;
        $mean  = 6.8;
        return ($votes * $ratingBgg + $C * $mean) / ($votes + $C);
    }

    /**
     * Jeux à ressortir : pas joués depuis six mois par ce joueur. Par défaut ceux de sa collection ;
     * avec `$library`, ceux de la ludothèque de l'association et des membres, sans seuil de note.
     */
    public function findForgottenGems(User $user, bool $library = false): array
    {
        $games = $library
            ? $this->gameRepository->findLibraryGames()
            : $this->gameRepository->findUserCollectionForRecommendation($user, null, null);

        // Une extension ne s'« oublie » pas : on ressort les jeux de base.
        $games = array_values(array_filter($games, fn(Game $g) => !$g->isExpansion()));
        if (empty($games)) {
            return [];
        }

        $sessionStats = $this->sessionRepository->getStatsForUser($user);
        // Une note personnelle (BGG ou MyLudo) prouve que le jeu a été joué : il n'est pas « oublié », on ne sait pas
        // quand on y a joué. Seuls restent les jeux sans note (jamais joués, ou dont la dernière partie enregistrée est ancienne).
        $userRatings = $this->userGameRepository->getBggUserRatings($user);
        $now = new \DateTimeImmutable();
        $threshold = $now->modify('-180 days');

        $gems = [];
        foreach ($games as $game) {
            if (($userRatings[$game->getId()] ?? 0) > 0) {
                continue;
            }

            $stats = $sessionStats[$game->getId()] ?? null;
            $lastPlayed = $stats['lastPlayedAt'] ?? null;

            $isOldOrNeverPlayed = $lastPlayed === null || $lastPlayed < $threshold;
            $isNotDisliked = ($stats['worstRating'] ?? null) !== 1;
            $hasGoodRating = $library || ($game->getRatingBgg() ?? 0) >= 7.0;

            if ($isOldOrNeverPlayed && $isNotDisliked && $hasGoodRating) {
                $gems[] = [
                    'game'   => $game,
                    'reason' => $this->buildForgottenReason($stats, $library),
                ];
            }
        }

        usort($gems, fn($a, $b) =>
            ($b['game']->getRatingBgg() ?? 0) <=> ($a['game']->getRatingBgg() ?? 0)
        );

        return $this->pickRandom($gems, 30, 10);
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

    /** Pourquoi ce jeu ressort : sa dernière partie enregistrée est ancienne, ou aucune n'est enregistrée. */
    private function buildForgottenReason(?array $stats, bool $library = false): string
    {
        $lastPlayed = $stats['lastPlayedAt'] ?? null;
        if ($lastPlayed !== null) {
            $months = max(1, intdiv((new \DateTimeImmutable())->diff($lastPlayed)->days, 30));
            return "Pas joué depuis {$months} mois";
        }

        return $library ? 'Aucune partie enregistrée, dans la ludothèque' : 'Aucune partie enregistrée, dans votre collection';
    }
}
