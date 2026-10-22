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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Re-fetche les extensions qui n'ont pas encore de lien vers leur jeu de base
 * (implements_bgg_ids = []).
 *
 * À lancer une fois après avoir ajouté la logique linkToBaseGames dans
 * GameEnrichmentService, pour mettre à jour les données existantes.
 *
 * Usage :
 *   php bin/console app:bgg:relink-expansions
 *   php bin/console app:bgg:relink-expansions --all   # re-fetche TOUTES les extensions, pas seulement les non-liées
 */
#[AsCommand(
    name: 'app:bgg:relink-expansions',
    description: 'Re-fetche les extensions sans lien vers leur jeu de base et les relie.',
)]
class RelinkExpansionsCommand extends Command
{
    public function __construct(
        private readonly GameRepository        $gameRepository,
        private readonly BggApiService         $bggApi,
        private readonly GameEnrichmentService $enrichment,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('all',   null, InputOption::VALUE_NONE, 'Re-fetche toutes les extensions, même celles déjà liées')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre max d\'extensions à traiter', 500)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $all   = $input->getOption('all');
        $limit = (int) $input->getOption('limit');

        $io->title('Re-liaison des extensions vers leurs jeux de base');

        // Récupérer les extensions à traiter
        $conn = $this->em->getConnection();
        $sql  = 'SELECT id FROM game WHERE is_expansion = TRUE'
              . ($all ? '' : " AND implements_bgg_ids::text = '[]'")
              . ' ORDER BY rating_bgg DESC NULLS LAST LIMIT ?';

        $ids = $conn->fetchFirstColumn($sql, [$limit]);

        if (empty($ids)) {
            $io->success('Aucune extension à traiter.');
            return Command::SUCCESS;
        }

        $io->note(sprintf('%d extension(s) à re-fetcher depuis BGG…', count($ids)));

        // Charger les entités
        $games = $this->gameRepository->findBy(['id' => $ids]);
        $bggIds = array_map(fn(Game $g) => $g->getBggId(), $games);
        $gameByBggId = [];
        foreach ($games as $g) {
            $gameByBggId[$g->getBggId()] = $g;
        }

        $updated = 0;
        $errors  = 0;
        $chunks  = array_chunk($bggIds, 20);
        $io->progressStart(count($chunks));

        foreach ($chunks as $chunk) {
            try {
                $dataList = $this->bggApi->fetchGamesDetails($chunk);
                foreach ($dataList as $data) {
                    $game = $gameByBggId[$data['bggId']] ?? null;
                    if ($game === null) {
                        continue;
                    }
                    $this->enrichment->hydrate($game, $data);
                    $updated++;
                }
                $this->em->flush();
            } catch (\Throwable $e) {
                $io->warning(sprintf('Erreur sur un lot : %s', $e->getMessage()));
                $errors++;
            }
            $io->progressAdvance();
        }

        $io->progressFinish();

        // Vérification post-traitement
        $stillUnlinked = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM game WHERE is_expansion = TRUE AND implements_bgg_ids::text = '[]'"
        );

        $io->success(sprintf(
            '%d extension(s) mises à jour, %d erreur(s). Extensions encore sans lien : %d.',
            $updated, $errors, $stillUnlinked
        ));

        return Command::SUCCESS;
    }
}
