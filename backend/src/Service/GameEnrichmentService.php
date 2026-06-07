<?php

namespace App\Service;

use App\Entity\Game;
use Doctrine\ORM\EntityManagerInterface;

class GameEnrichmentService
{
    public function __construct(
        private readonly BggApiService $bggApi,
        private readonly EntityManagerInterface $em,
        private readonly MechanicFamilyResolver $familyResolver,
    ) {}

    /**
     * Enrichit le jeu avec les données complètes de l'API BGG si besoin.
     * Un jeu issu uniquement du CSV (source = 'bgg_csv') n'a pas encore
     * de description, d'image ni de données de joueurs — on les récupère ici.
     */
    public function enrichIfNeeded(Game $game): void
    {
        $needsEnrichment = $game->getSource() === 'bgg_csv'
            || $game->getMechanics() === [];

        $needsResync = !$needsEnrichment
            && $game->getSource() === 'bgg_api'
            && (
                $game->getLastSyncedAt() === null                                        // jamais synchronisé
                || $game->getLastSyncedAt() < new \DateTimeImmutable('-30 days')         // resync mensuel
            );

        if (!$needsEnrichment && !$needsResync) {
            return;
        }

        try {
            $results = $this->bggApi->fetchGamesDetails([$game->getBggId()]);
        } catch (\Throwable) {
            return;
        }

        if (empty($results)) {
            return;
        }

        $this->hydrate($game, $results[0]);
        $this->em->flush();
    }

    public function hydrate(Game $game, array $data): Game
    {
        $game
            ->setBggId($data['bggId'])
            ->setName($data['name'])
            // Ne pas écraser une valeur existante par null (BGG peut renvoyer vide selon les jeux)
            ->setDescription($data['description'] ?? $game->getDescription())
            ->setMinPlayers($data['minPlayers'])
            ->setMaxPlayers($data['maxPlayers'])
            ->setPlayingTime($data['playingTime'])
            ->setComplexity($data['complexity'] > 0 ? $data['complexity'] : $game->getComplexity())
            ->setCategories(!empty($data['categories']) ? $data['categories'] : $game->getCategories())
            ->setMechanics(!empty($data['mechanics'])   ? $data['mechanics']   : $game->getMechanics())
            ->setImageUrl($data['imageUrl'] ?? $game->getImageUrl())
            ->setYearPublished($data['yearPublished'] ?? $game->getYearPublished())
            ->setRatingBgg($data['ratingBgg'] ?? $game->getRatingBgg())
            ->setBggRank($data['bggRank']    ?? $game->getBggRank())
            ->setUsersRated($data['usersRated'] ?? $game->getUsersRated())
            ->setExpansionBggIds(!empty($data['expansionIds'])  ? $data['expansionIds']  : $game->getExpansionBggIds())
            ->setImplementsBggIds(!empty($data['implementsIds']) ? $data['implementsIds'] : $game->getImplementsBggIds())
            ->setSource('bgg_api')
            ->setLastSyncedAt(new \DateTimeImmutable());

        $resolved = $this->familyResolver->resolve($game->getMechanics());
        $game->setMechanicFamilies($resolved['families']);
        $game->setDetectedEngines($resolved['detectedEngines']);

        return $game;
    }

    /** Recalcule les familles et engines d'un jeu déjà enrichi sans rappeler l'API BGG. */
    public function recomputeFamilies(Game $game): void
    {
        $resolved = $this->familyResolver->resolveForGame($game);
        $game->setMechanicFamilies($resolved['families']);
        $game->setDetectedEngines($resolved['detectedEngines']);
    }
}
