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
     * Premier enrichissement à la demande, pour un joueur qui ouvre la fiche d'un
     * jeu issu du CSV (ni description, ni image, ni mécaniques). Le rafraîchissement
     * périodique, lui, appartient au cron (app:bgg:enrich-pending --mode=resync).
     *
     * Un seul essai par jour et par jeu, réussi ou non : sans cela, un jeu absent
     * de BGG ou une panne rappellerait l'API à chaque consultation.
     */
    public function enrichIfNeeded(Game $game): void
    {
        if ($game->getSource() !== 'bgg_csv' && $game->getMechanics() !== []) {
            return;
        }

        $lastAttempt = $game->getLastFetchAttemptAt();
        if ($lastAttempt !== null && $lastAttempt > new \DateTimeImmutable('-1 day')) {
            return;
        }

        $game->setLastFetchAttemptAt(new \DateTimeImmutable());

        try {
            $results = $this->bggApi->fetchGamesDetails([$game->getBggId()]);
            if ($results !== []) {
                $this->hydrate($game, $results[0]);
            }
        } catch (\Throwable) {
            // BGG indisponible : la fiche s'affiche avec ce qu'on a déjà.
        }

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
            ->setPublisherOriginal($data['publisherOriginal'] ?? $game->getPublisherOriginal())
            ->setEditionFrPublisher($data['editionFr']['publisher'] ?? $game->getEditionFrPublisher())
            ->setEditionFrYear($data['editionFr']['year'] ?? $game->getEditionFrYear())
            ->setEditionFrImageUrl($data['editionFr']['imageUrl'] ?? $game->getEditionFrImageUrl())
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
            ->setLastSyncedAt($now = new \DateTimeImmutable())
            ->setLastFetchAttemptAt($now);

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
                $baseGame->setExpansionBggIds(array_keys(array_flip([...$current, $expansion->getBggId()])));
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

    /** Recalcule les familles et engines d'un jeu déjà enrichi sans rappeler l'API BGG. */
    public function recomputeFamilies(Game $game): void
    {
        $resolved = $this->familyResolver->resolveForGame($game);
        $game->setMechanicFamilies($resolved['families']);
        $game->setDetectedEngines($resolved['detectedEngines']);
    }
}
