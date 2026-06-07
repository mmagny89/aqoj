<?php

namespace App\Command;

use App\Repository\GameRepository;
use App\Service\BggApiService;
use App\Service\GameEnrichmentService;
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
 * Les deux modes traitent par lots de 20 IDs (limite recommandée par l'API BGG).
 * En cas d'erreur sur un lot, on loggue et on continue — la tâche ne tombe jamais en rade.
 */
#[AsCommand(
    name: 'app:bgg:enrich-pending',
    description: 'Enrichit / resynchronise les jeux avec l\'API BGG (quotidien, incrémental)',
)]
class EnrichGamesPendingCommand extends Command
{
    private const BATCH_SIZE = 20;

    public function __construct(
        private readonly GameRepository $repository,
        private readonly BggApiService $bggApi,
        private readonly GameEnrichmentService $enrichment,
        private readonly EntityManagerInterface $em,
        private readonly ManagerRegistry $doctrine,
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
            )
            ->addOption(
                'sleep',
                null,
                InputOption::VALUE_REQUIRED,
                'Pause en ms entre chaque lot de 20 jeux (min. recommandé : 2000 pour éviter le rate-limit BGG)',
                2000
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $mode    = $input->getOption('mode');
        $limit   = max(0, (int) $input->getOption('limit'));
        $sleepUs = max(0, (int) $input->getOption('sleep')) * 1000;

        if (!in_array($mode, ['pending', 'resync'], true)) {
            $io->error("Mode invalide : {$mode}. Valeurs : pending, resync.");
            return Command::FAILURE;
        }

        $io->title("Enrichissement BGG — mode : {$mode}");

        $bggIds = $mode === 'pending'
            ? $this->repository->findPendingEnrichmentIds($limit)
            : $this->repository->findStaleForResync($limit);

        $total = count($bggIds);

        if ($total === 0) {
            $io->success('Aucun jeu à traiter.');
            return Command::SUCCESS;
        }

        $io->text(sprintf('%d jeux à traiter (lots de %d)…', $total, self::BATCH_SIZE));

        $progressBar = new ProgressBar($output, $total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — écoulé: %elapsed:6s%  restant: ~%remaining:6s%');
        $progressBar->start();

        $enriched = 0;
        $errors   = 0;
        $batches  = array_chunk($bggIds, self::BATCH_SIZE);

        foreach ($batches as $i => $chunk) {
            try {
                $games = $this->repository->findByBggIds($chunk);
                $byBggId = [];
                foreach ($games as $game) {
                    $byBggId[$game->getBggId()] = $game;
                }

                $apiResults = $this->bggApi->fetchGamesDetails($chunk);

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

            if ($i < count($batches) - 1 && $sleepUs > 0) {
                usleep($sleepUs);
            }
        }

        $progressBar->finish();
        $io->newLine(2);
        $io->success(sprintf('%d jeux traités, %d erreurs sur %d.', $enriched, $errors, $total));

        return Command::SUCCESS;
    }
}
