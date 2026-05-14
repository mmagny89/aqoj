<?php

namespace App\Command;

use App\Repository\GameRepository;
use App\Service\BggApiService;
use App\Service\GameEnrichmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:bgg:enrich-pending',
    description: 'Enrichit les jeux (source=bgg_csv) avec les données complètes de l\'API BGG',
)]
class EnrichGamesPendingCommand extends Command
{
    private const BATCH_SIZE = 20;

    public function __construct(
        private readonly GameRepository $repository,
        private readonly BggApiService $bggApi,
        private readonly GameEnrichmentService $enrichment,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Nombre de jeux à enrichir (0 = tous)', 2000)
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Pause en ms entre chaque appel API', 1000);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = max(0, (int) $input->getOption('limit'));
        $sleepUs = max(0, (int) $input->getOption('sleep')) * 1000;

        $io->title('Enrichissement BGG');

        $bggIds = $this->repository->findPendingEnrichmentIds($limit);
        $total = count($bggIds);

        if ($total === 0) {
            $io->success('Aucun jeu en attente d\'enrichissement.');
            return Command::SUCCESS;
        }

        $io->text("$total jeux à enrichir par lots de " . self::BATCH_SIZE . "…");

        $progressBar = new ProgressBar($output, $total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %elapsed:6s% restant: ~%remaining:6s%');
        $progressBar->start();

        $enriched = 0;
        $errors = 0;
        $batches = array_chunk($bggIds, self::BATCH_SIZE);

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
                $this->em->clear();
            } catch (\Throwable $e) {
                $errors += count($chunk);
                $io->newLine();
                $io->warning('Batch ' . ($i + 1) . ' échoué : ' . $e->getMessage());
            }

            $progressBar->advance(count($chunk));

            if ($i < count($batches) - 1 && $sleepUs > 0) {
                usleep($sleepUs);
            }
        }

        $progressBar->finish();
        $io->newLine(2);
        $io->success(sprintf('%d jeux enrichis, %d erreurs.', $enriched, $errors));

        return Command::SUCCESS;
    }
}
