<?php

namespace App\Command;

use App\Entity\Game;
use App\Repository\GameRepository;
use App\Service\BggApiService;
use App\Service\GameEnrichmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Tâche cron quotidienne : découverte des nouvelles sorties BGG.
 *
 * Sources :
 *   1. BGG Hot List (/xmlapi2/hot?type=boardgame) — top 50 jeux du moment
 *   2. BGG Top 100 de l'année en cours (/xmlapi2/search?type=boardgame&query=*)
 *      — filtré sur yearPublished = année courante
 *
 * Pour chaque jeu découvert absent de la base : enrichissement complet immédiat.
 * Les jeux déjà présents sont ignorés (ils seront resynchronisés par app:bgg:enrich-pending --mode=resync).
 */
#[AsCommand(
    name: 'app:bgg:sync-new',
    description: 'Importe les nouvelles sorties BGG (Hot List + top de l\'année)',
)]
class SyncBggNewReleasesCommand extends Command
{
    private const BGG_API = 'https://boardgamegeek.com/xmlapi2';

    public function __construct(
        private readonly GameRepository $repository,
        private readonly BggApiService $bggApi,
        private readonly GameEnrichmentService $enrichment,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Sync BGG — nouvelles sorties');

        $discovered = [];

        // ── 1. BGG Hot List ─────────────────────────────────────────────────
        $io->section('Hot List BGG');
        try {
            $hotIds = $this->bggApi->fetchHotList();
            $io->text(sprintf('%d jeux dans la Hot List.', count($hotIds)));
            $discovered = array_merge($discovered, $hotIds);
        } catch (\Throwable $e) {
            $io->warning('Impossible de récupérer la Hot List : ' . $e->getMessage());
        }

        // ── 2. Nouveautés de l'année courante ────────────────────────────────
        $io->section('Nouveautés ' . date('Y'));
        try {
            $currentYear = (int) date('Y');
            $newIds = [];
            foreach (['game', 'jeu', 'spiel'] as $term) {
                $found = $this->bggApi->searchByYear($term, $currentYear);
                $newIds = array_merge($newIds, $found);
                if (count($newIds) >= 100) break;
                usleep(300_000);
            }
            $newIds = array_values(array_unique($newIds));
            $io->text(sprintf('%d jeux publiés en %d trouvés sur BGG.', count($newIds), $currentYear));
            $discovered = array_merge($discovered, $newIds);
        } catch (\Throwable $e) {
            $io->warning('Impossible de récupérer les nouveautés : ' . $e->getMessage());
        }

        // ── Dédoublonnage et filtrage des inconnus ───────────────────────────
        $uniqueIds = array_values(array_unique($discovered));

        if (empty($uniqueIds)) {
            $io->warning('Aucun ID BGG récupéré.');
            return Command::SUCCESS;
        }

        // On ne garde que les IDs absents de notre base
        $existingIds = $this->repository->findExistingBggIds($uniqueIds);
        $newIds      = array_values(array_diff($uniqueIds, $existingIds));

        $io->text(sprintf(
            '%d IDs récupérés, %d déjà en base → %d nouveaux à importer.',
            count($uniqueIds),
            count($existingIds),
            count($newIds)
        ));

        if (empty($newIds)) {
            $io->success('Aucune nouveauté à importer.');
            return Command::SUCCESS;
        }

        // ── Import des nouveaux jeux ─────────────────────────────────────────
        $imported = 0;
        $errors   = 0;

        foreach (array_chunk($newIds, 20) as $chunk) {
            try {
                $gamesData = $this->bggApi->fetchGamesDetails($chunk);
                foreach ($gamesData as $data) {
                    // Ignorer si déjà entré en base par concurrence
                    if ($this->repository->existsByBggId($data['bggId'])) {
                        continue;
                    }
                    $game = $this->enrichment->hydrate(new Game(), $data);
                    $this->em->persist($game);
                    $imported++;
                }
                $this->em->flush();
                $this->em->clear();
            } catch (\Throwable $e) {
                $errors += count($chunk);
                $io->warning('Erreur lot : ' . $e->getMessage());
            }
            usleep(500_000); // 0.5s entre les lots pour respecter le rate-limit BGG
        }

        $io->success(sprintf('%d nouveaux jeux importés, %d erreurs.', $imported, $errors));

        return Command::SUCCESS;
    }

}
