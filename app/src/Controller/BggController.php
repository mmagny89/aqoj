<?php

namespace App\Controller;

use App\Entity\Game;
use App\Repository\AssociationGameRepository;
use App\Repository\GameRepository;
use App\Repository\UserGameRepository;
use App\Service\BggApiService;
use App\Service\GameEnrichmentService;
use App\Service\UserPreferenceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class BggController extends AbstractController
{
    use CurrentUser;

    private const MAX_SYNC_ENRICH = 100;

    #[Route('/api/bgg/import', name: 'api_bgg_import', methods: ['POST'])]
    public function import(
        Request $request,
        BggApiService $bggService,
        GameEnrichmentService $enrichment,
        EntityManagerInterface $em,
        GameRepository $gameRepository,
        UserGameRepository $userGameRepository,
        UserPreferenceService $prefService,
        AssociationGameRepository $associationGames,
    ): JsonResponse {
        $user = $this->appUser();
        if (!$user) {
            return $this->json(['error' => 'Authentification requise.'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $target = $data['target'] ?? 'me';
        if (!in_array($target, ['me', 'association'], true)) {
            return $this->json(['error' => 'Destination inconnue.'], 400);
        }
        // La ludothèque de l'association ne se renseigne que par un administrateur, avec le pseudo BGG
        // de l'association : jamais celui de l'administrateur, qui reste attaché à son propre profil.
        $forAssociation = $target === 'association';
        if ($forAssociation && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Réservé aux administrateurs.'], 403);
        }
        $username = trim($data['username'] ?? ($forAssociation ? '' : $user->getBggUsername()) ?? '');

        if ($username === '') {
            return $this->json(['error' => 'Le username BGG est requis'], 400);
        }

        // Sauvegarder le username BGG sur le profil utilisateur
        if (!$forAssociation && $user->getBggUsername() !== $username) {
            $user->setBggUsername($username);
        }

        try {
            // Un seul appel : la collection complète, extensions comprises (chaque item porte son sous-type).
            $collectionItems = $bggService->fetchCollection($username, true);
        } catch (\RuntimeException $e) {
            // Seul le refus d'accès (collection privée) est expliqué ; le reste ne détaille pas BGG au client.
            $message = $e->getCode() === 401 ? $e->getMessage() : 'BoardGameGeek est momentanément indisponible. Réessayez plus tard.';

            return $this->json(['error' => $message], 502);
        }

        if (empty($collectionItems)) {
            $em->flush();
            return $this->json([
                'imported' => 0,
                'total' => 0,
                'skipped' => 0,
                'message' => 'Aucun jeu trouvé pour cet utilisateur BGG.',
            ]);
        }

        $bggIds = array_column($collectionItems, 'bggId');
        // Map bggId => userRating (null si non noté)
        $userRatingsByBggId = array_column($collectionItems, 'userRating', 'bggId');

        // Récupérer les jeux déjà en catalogue
        $existingGames = $gameRepository->findByBggIds($bggIds);
        $existingByBggId = [];
        foreach ($existingGames as $game) {
            $existingByBggId[$game->getBggId()] = $game;
        }

        $newBggIds = array_values(array_diff($bggIds, array_keys($existingByBggId)));
        $imported = 0;

        // Importer les jeux manquants depuis l'API BGG
        foreach (array_chunk($newBggIds, 20) as $chunk) {
            try {
                $gamesData = $bggService->fetchGamesDetails($chunk);
            } catch (\RuntimeException) {
                // BGG indisponible : insister aggraverait la limitation. Les jeux manquants seront repris par le cron.
                break;
            }

            foreach ($gamesData as $gameData) {
                $game = $enrichment->hydrate(new Game(), $gameData);
                $em->persist($game);
                $existingByBggId[$gameData['bggId']] = $game;
                $imported++;
            }

            $em->flush();
        }

        // Lier tous les jeux de la collection BGG à l'utilisateur (ou à la ludothèque de l'association)
        $linked = 0;
        $associationIds = [];
        foreach ($bggIds as $bggId) {
            $game = $existingByBggId[$bggId] ?? null;
            if ($game) {
                if ($forAssociation) {
                    $associationIds[] = $game->getId();
                } else {
                    $user->addGame($game);
                }
                $linked++;
            }
        }

        $em->flush();

        if ($forAssociation) {
            $associationGames->addMany($associationIds, $user);
        } else {
            // Sauvegarder les notes personnelles BGG
            $userGameRepository->saveRatingsForUser($user, $userRatingsByBggId);
        }

        // Enrichir les jeux de la collection qui ont encore mechanics = [] : les 100 premiers seulement
        // (5 appels), pour que les préférences calculées ci-dessous reposent sur des données ;
        // le reste passe en tête de file du cron (app:bgg:enrich-pending), qui sert d'abord les jeux possédés.
        $toEnrich = array_slice(array_values(array_filter(
            array_map(fn($id) => $existingByBggId[$id] ?? null, $bggIds),
            fn($g) => $g !== null && $g->getMechanics() === []
        )), 0, self::MAX_SYNC_ENRICH);

        $enriched = 0;
        foreach (array_chunk($toEnrich, 20) as $chunk) {
            try {
                $chunkBggIds = array_map(fn($g) => $g->getBggId(), $chunk);
                $gamesData   = $bggService->fetchGamesDetails($chunkBggIds);
                $dataByBggId = [];
                foreach ($gamesData as $d) {
                    $dataByBggId[$d['bggId']] = $d;
                }
                foreach ($chunk as $game) {
                    if (isset($dataByBggId[$game->getBggId()])) {
                        $enrichment->hydrate($game, $dataByBggId[$game->getBggId()]);
                        $enriched++;
                    }
                }
                $em->flush();
            } catch (\RuntimeException) {
                break;
            }
        }

        // Recalculer les préférences après import (celles de l'administrateur ne dépendent pas de la ludothèque commune)
        if (!$forAssociation) {
            try {
                $prefService->recompute($user);
            } catch (\Throwable) { /* non bloquant */ }
        }

        $skipped = count($bggIds) - $imported;

        return $this->json([
            'imported'  => $imported,
            'enriched'  => $enriched,
            'total'     => count($bggIds),
            'skipped'   => $skipped,
            'bggUsername' => $username,
            'target'    => $target,
            'message'   => "{$imported} nouveaux jeux ajoutés, {$enriched} jeux enrichis depuis BGG, {$linked} jeux liés à " . ($forAssociation ? 'la ludothèque de l\'association.' : 'votre collection.'),
        ]);
    }
}
