<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\DBAL\Connection;

/**
 * Accès DBAL à la table user_game pour les données propres à la relation
 * utilisateur ↔ jeu (note BGG personnelle, etc.).
 */
class UserGameRepository
{
    public function __construct(private readonly Connection $conn) {}

    /**
     * Retourne la map [gameId => bggUserRating|null] pour tous les jeux
     * de la collection d'un utilisateur.
     *
     * @return array<int, float|null>
     */
    public function getBggUserRatings(User $user): array
    {
        $rows = $this->conn->fetchAllAssociative(
            'SELECT game_id, bgg_user_rating FROM user_game WHERE user_id = ?',
            [$user->getId()]
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['game_id']] = $row['bgg_user_rating'] !== null
                ? (float) $row['bgg_user_rating']
                : null;
        }

        return $map;
    }

    /**
     * Retourne le set des gameId (int) possédés par l'utilisateur.
     *
     * @return array<int, int>  clés = gameId (à tester avec isset)
     */
    public function getOwnedGameIds(User $user): array
    {
        $rows = $this->conn->fetchFirstColumn(
            'SELECT game_id FROM user_game WHERE user_id = ?',
            [$user->getId()]
        );

        return array_flip(array_map('intval', $rows));
    }

    /**
     * Sauvegarde en masse les notes BGG utilisateur lors de l'import.
     *
     * @param array<string, float|null> $ratingsByBggId  bggId => rating
     */
    public function saveRatingsForUser(User $user, array $ratingsByBggId): void
    {
        if (empty($ratingsByBggId)) {
            return;
        }

        // Récupère les game_id correspondant aux bggIds
        $bggIds = array_keys($ratingsByBggId);
        $placeholders = implode(', ', array_fill(0, count($bggIds), '?'));

        $rows = $this->conn->fetchAllAssociative(
            "SELECT id, bgg_id FROM game WHERE bgg_id IN ({$placeholders})",
            $bggIds
        );

        foreach ($rows as $row) {
            $rating = $ratingsByBggId[$row['bgg_id']] ?? null;
            $this->conn->executeStatement(
                'UPDATE user_game SET bgg_user_rating = ? WHERE user_id = ? AND game_id = ?',
                [$rating, $user->getId(), $row['id']]
            );
        }
    }

    /**
     * Ajoute des jeux à la ludothèque d'un joueur (ceux qui y sont déjà restent) et pose la note personnelle
     * quand elle est fournie, sans jamais effacer une note existante.
     *
     * @param array<int, float|null> $ratingsByGameId gameId => note (null = pas de note)
     *
     * @return array{added: int, existing: int}
     */
    public function addMany(User $user, array $ratingsByGameId): array
    {
        if ($ratingsByGameId === []) {
            return ['added' => 0, 'existing' => 0];
        }

        $existing = count(array_intersect_key($ratingsByGameId, $this->getOwnedGameIds($user)));

        foreach ($ratingsByGameId as $gameId => $rating) {
            $this->conn->executeStatement(
                'INSERT INTO user_game (user_id, game_id, bgg_user_rating) VALUES (?, ?, ?)
                 ON CONFLICT (user_id, game_id) DO UPDATE
                 SET bgg_user_rating = COALESCE(EXCLUDED.bgg_user_rating, user_game.bgg_user_rating)',
                [$user->getId(), $gameId, $rating],
            );
        }

        return ['added' => count($ratingsByGameId) - $existing, 'existing' => $existing];
    }
}
