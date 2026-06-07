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
     * @return array<int, true>  clés = gameId, valeurs = true (isset-friendly)
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
}
