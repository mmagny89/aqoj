<?php

namespace App\Service;

use App\Entity\Game;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;

class GameEnrichmentService
{
    public function __construct(
        private readonly BggApiService $bggApi,
        private readonly EntityManagerInterface $em,
        private readonly MechanicFamilyResolver $familyResolver,
        private readonly GameRepository $gameRepository,
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
        $originalName = $data['name'];
        $nameFr       = $data['nameFr'] ?? null;
        // Le nom d'affichage : FR si détecté, sinon nom original BGG
        $displayName  = $nameFr ?: $originalName;

        $bggType = $data['bggType'] ?? 'boardgame';

        $game
            ->setBggId($data['bggId'])
            ->setName($displayName)
            ->setNameOriginal($originalName)
            ->setBggType($bggType)
            ->setIsExpansion($bggType === 'boardgameexpansion')
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
            ->setMinAge($data['minAge'] ?? $game->getMinAge())
            ->setExpansionBggIds(!empty($data['expansionIds'])  ? $data['expansionIds']  : $game->getExpansionBggIds())
            ->setImplementsBggIds($this->buildImplementsIds(
                $data['baseGameIds']   ?? [],
                $data['implementsIds'] ?? [],
                $game->getImplementsBggIds()
            ))
            ->setSource('bgg_api')
            ->setLastSyncedAt(new \DateTimeImmutable());

        $resolved = $this->familyResolver->resolve($game->getMechanics());
        $game->setMechanicFamilies($resolved['families']);
        $game->setDetectedEngines($resolved['detectedEngines']);

        // Si c'est une extension, lier le(s) jeu(x) de base
        if ($bggType === 'boardgameexpansion' && !empty($data['baseGameIds'])) {
            $this->linkToBaseGames($game, $data['baseGameIds']);
        }

        return $game;
    }

    /**
     * Met à jour les jeux de base pour qu'ils incluent l'extension dans leur expansionBggIds.
     */
    private function linkToBaseGames(Game $expansion, array $baseGameBggIds): void
    {
        foreach ($baseGameBggIds as $baseId) {
            $baseGame = $this->gameRepository->findOneBy(['bggId' => $baseId]);
            if ($baseGame === null) {
                continue;
            }
            $current = $baseGame->getExpansionBggIds();
            if (!in_array($expansion->getBggId(), $current, true)) {
                $baseGame->setExpansionBggIds(array_values(array_unique([...$current, $expansion->getBggId()])));
            }
        }
    }

    /**
     * Construit la liste d'IDs implements_bgg_ids en fusionnant :
     * - baseGameIds   : IDs du jeu de base (pour les extensions BGG)
     * - implementsIds : IDs de re-implémentations
     * Si les deux nouvelles sources sont vides, on conserve la valeur existante.
     */
    private function buildImplementsIds(array $baseGameIds, array $implementsIds, array $existing): array
    {
        $merged = array_values(array_unique(array_merge($baseGameIds, $implementsIds)));
        return !empty($merged) ? $merged : $existing;
    }

    public function getBggApiService(): BggApiService
    {
        return $this->bggApi;
    }

    /** Recalcule les familles et engines d'un jeu déjà enrichi sans rappeler l'API BGG. */
    public function recomputeFamilies(Game $game): void
    {
        $resolved = $this->familyResolver->resolveForGame($game);
        $game->setMechanicFamilies($resolved['families']);
        $game->setDetectedEngines($resolved['detectedEngines']);
    }
}
