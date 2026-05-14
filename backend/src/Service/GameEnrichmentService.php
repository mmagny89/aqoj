<?php

namespace App\Service;

use App\Entity\Game;
use Doctrine\ORM\EntityManagerInterface;

class GameEnrichmentService
{
    public function __construct(
        private readonly BggApiService $bggApi,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Enrichit le jeu avec les données complètes de l'API BGG si besoin.
     * Un jeu issu uniquement du CSV (source = 'bgg_csv') n'a pas encore
     * de description, d'image ni de données de joueurs — on les récupère ici.
     */
    public function enrichIfNeeded(Game $game): void
    {
        $needsEnrichment = $game->getSource() === 'bgg_csv';
        $needsResync = $game->getSource() === 'bgg_api'
            && $game->getLastSyncedAt() !== null
            && $game->getLastSyncedAt() < new \DateTimeImmutable('-30 days');

        if (!$needsEnrichment && !$needsResync) {
            return;
        }

        try {
            $results = $this->bggApi->fetchGamesDetails([$game->getBggId()]);
        } catch (\Throwable) {
            return; // BGG indisponible — on retourne les données CSV telles quelles
        }

        if (empty($results)) {
            return;
        }

        $this->hydrate($game, $results[0]);
        $this->em->flush();
    }

    public function hydrate(Game $game, array $data): Game
    {
        return $game
            ->setBggId($data['bggId'])
            ->setName($data['name'])
            ->setDescription($data['description'])
            ->setMinPlayers($data['minPlayers'])
            ->setMaxPlayers($data['maxPlayers'])
            ->setPlayingTime($data['playingTime'])
            ->setComplexity($data['complexity'])
            ->setCategories($data['categories'])
            ->setMechanics($data['mechanics'])
            ->setImageUrl($data['imageUrl'])
            ->setYearPublished($data['yearPublished'])
            ->setRatingBgg($data['ratingBgg'] ?? $game->getRatingBgg())
            ->setSource('bgg_api')
            ->setLastSyncedAt(new \DateTimeImmutable());
    }
}
