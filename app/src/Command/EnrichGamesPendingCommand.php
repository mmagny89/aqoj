<?php

namespace App\Command;

use App\Repository\GameRepository;
use App\Service\BggApiException;
use App\Service\BggApiService;
use App\Service\BggJobLock;
use App\Service\GameEnrichmentService;
use App\Service\JobRunRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Tâche cron quotidienne d'enrichissement BGG.
 *
 * Deux modes selon --mode :
 *
 *   pending  : jeux importés depuis un CSV BGG (source=bgg_csv) → premier enrichissement complet.
 *              Priorité par rang BGG (populaires d'abord).
 *
 *   resync   : jeux déjà enrichis (source=bgg_api) → resynchronisation incrémentale.
 *              Priorité aux jeux les plus anciennement synchronisés (last_synced_at ASC NULLS FIRST).
 *              Avec --limit=300 et ~9000 jeux : cycle complet en ~30 jours.
 *
 * Les deux modes traitent par lots de 20 IDs (limite de l'API BGG). La cadence
 * (5 s entre deux appels) et les nouvelles tentatives sont réglées par BggApiService.
 *
 * Chaque lot est enregistré dès qu'il est traité : une tâche interrompue reprend
 * sur les jeux restants. Un jeu absent de la réponse est daté quand même
 * (last_fetch_attempt_at), sinon il reviendrait en tête de chaque passage. Si BGG
 * reste indisponible, la tâche s'arrête : insister sur les lots suivants ne ferait
 * que durcir la limitation.
 * Un verrou empêche deux passages simultanés (avec app:bgg:sync-new notamment).
 */
#[AsCommand(
    name: 'app:bgg:enrich-pending',
    description: 'Enrichit / resynchronise les jeux avec l\'API BGG (quotidien, incrémental)',
)]
class EnrichGamesPendingCommand extends Command
{
    public function __construct(
        private readonly GameRepository $repository,
        private readonly BggApiService $bggApi,
        private readonly GameEnrichmentService $enrichment,
        private readonly EntityManagerInterface $em,
        private readonly ManagerRegistry $doctrine,
        private readonly BggJobLock $lock,
        private readonly JobRunRecorder $recorder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'mode',
                'm',
                InputOption::VALUE_REQUIRED,
                'pending = premiers enrichissements (bgg_csv), resync = rafraîchissement incrémental (bgg_api)',
                'pending'
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Nombre maximum de jeux à traiter (0 = tous — attention : peut prendre plusieurs heures)',
                300
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $mode    = $input->getOption('mode');
        $limit   = max(0, (int) $input->getOption('limit'));

        if (!in_array($mode, ['pending', 'resync'], true)) {
            $io->error("Mode invalide : {$mode}. Valeurs : pending, resync.");
            return Command::FAILURE;
        }

        if (!$this->lock->acquire()) {
            $io->warning('Une autre tâche BGG est déjà en cours : ce passage est sauté.');
            $this->recorder->note(['skipped' => 'verrou BGG pris']);
            return Command::SUCCESS;
        }

        try {
            return $this->enrich($io, $output, $mode, $limit);
        } finally {
            $this->lock->release();
        }
    }

    private function enrich(SymfonyStyle $io, OutputInterface $output, string $mode, int $limit): int
    {
        $io->title("Enrichissement BGG — mode : {$mode}");

        $bggIds = $mode === 'pending'
            ? $this->repository->findPendingEnrichmentIds($limit)
            : $this->repository->findStaleForResync($limit);

        $total = count($bggIds);
        $this->recorder->note(['mode' => $mode, 'limite' => $limit, 'selectionnes' => $total]);

        if ($total === 0) {
            $io->success('Aucun jeu à traiter.');
            return Command::SUCCESS;
        }

        $io->text(sprintf('%d jeux à traiter (lots de %d)…', $total, BggApiService::MAX_IDS));

        $progressBar = new ProgressBar($output, $total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — écoulé: %elapsed:6s%  restant: ~%remaining:6s%');
        $progressBar->start();

        $enriched = 0;
        $errors   = 0;
        $batches  = array_chunk($bggIds, BggApiService::MAX_IDS);

        foreach ($batches as $i => $chunk) {
            try {
                $games = $this->repository->findByBggIds($chunk);
                $byBggId = [];
                foreach ($games as $game) {
                    $byBggId[$game->getBggId()] = $game;
                }

                $apiResults = $this->bggApi->fetchGamesDetails($chunk);

                // Date tout le lot, y compris les jeux que BGG n'a pas renvoyés.
                $this->repository->markFetchAttempted($chunk);

                foreach ($apiResults as $data) {
                    $game = $byBggId[$data['bggId']] ?? null;
                    if ($game === null) {
                        continue;
                    }
                    $this->enrichment->hydrate($game, $data);
                    $enriched++;
                }

                $this->em->flush();
                // Libère la mémoire Doctrine après chaque lot
                $this->em->clear();

            } catch (BggApiException $e) {
                // BGG indisponible ou limitant : les lots déjà traités sont acquis, la suite attend le prochain passage.
                $progressBar->clear();
                $io->error('Arrêt : ' . $e->getMessage());
                $this->recorder->note(['enrichis' => $enriched, 'erreurs' => $errors, 'arret_bgg' => $e->getMessage()]);
                return Command::FAILURE;
            } catch (\Throwable $e) {
                $errors += count($chunk);
                $io->newLine();
                $io->warning(sprintf('Lot %d/%d échoué : %s', $i + 1, count($batches), $e->getMessage()));

                // Si Doctrine a fermé l'EntityManager suite à une exception DB
                // (ex : séquence UTF-8 invalide), on le réinitialise pour que les
                // lots suivants puissent continuer normalement.
                if (!$this->em->isOpen()) {
                    $this->doctrine->resetManager();
                }
            }

            $progressBar->advance(count($chunk));

        }

        $progressBar->finish();
        $io->newLine(2);
        $this->recorder->note(['enrichis' => $enriched, 'erreurs' => $errors]);
        $io->success(sprintf('%d jeux traités, %d erreurs sur %d.', $enriched, $errors, $total));

        return Command::SUCCESS;
    }
}
