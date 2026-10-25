<?php

namespace App\Command;

use App\Repository\GameRepository;
use App\Service\JobRunRecorder;
use App\Translation\DescriptionTranslator;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Traduit en français les descriptions qui attendent : un petit lot à chaque passage du planificateur,
 * dans une limite de temps, pour ne jamais retenir le worker qui porte aussi les tâches BGG de la nuit.
 * Ordre : les jeux de l'association, puis les jeux possédés par des membres, puis les mieux classés.
 */
#[AsCommand(name: 'app:translate:descriptions', description: 'Traduit en français les descriptions de jeux qui attendent')]
final class TranslateDescriptionsCommand extends Command
{
    public function __construct(
        private readonly Connection $conn,
        private readonly GameRepository $games,
        private readonly EntityManagerInterface $em,
        private readonly DescriptionTranslator $translator,
        private readonly JobRunRecorder $recorder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre de jeux au plus par passage', '60')
            ->addOption('time-budget', null, InputOption::VALUE_REQUIRED, 'Secondes au plus par passage (aucun nouveau jeu commencé au-delà)', '180')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Compte et liste ce qui serait traduit, sans appeler le service');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit  = max(1, (int) $input->getOption('limit'));
        $budget = max(10, (int) $input->getOption('time-budget'));

        $ids = $this->conn->fetchFirstColumn(
            "SELECT g.id FROM game g
             WHERE g.description IS NOT NULL AND g.description <> ''
               AND g.description_fr_hash IS DISTINCT FROM md5(g.description)
               AND (g.description_fr_attempt_at IS NULL OR g.description_fr_attempt_at <= NOW() - INTERVAL '1 day')
             ORDER BY EXISTS (SELECT 1 FROM association_game ag WHERE ag.game_id = g.id) DESC,
                      EXISTS (SELECT 1 FROM user_game ug WHERE ug.game_id = g.id) DESC,
                      g.bgg_rank ASC NULLS LAST, g.users_rated DESC NULLS LAST, g.id
             LIMIT ?",
            [$limit],
        );

        if ($input->getOption('dry-run')) {
            $io->success(count($ids) . ' jeu(x) à traduire dans ce passage.');

            return Command::SUCCESS;
        }

        $done = 0;
        $failed = 0;
        $started = microtime(true);
        foreach ($ids as $id) {
            if (microtime(true) - $started > $budget) {
                break;
            }
            $game = $this->games->find((int) $id);
            if ($game === null) {
                continue;
            }
            if ($this->translator->translate($game)) {
                ++$done;
            } else {
                ++$failed;
                // Service en panne : inutile d'enchaîner cinquante essais qui échoueront de la même façon.
                if ($failed >= 3 && $done === 0) {
                    $io->warning('Le service de traduction ne répond pas : passage interrompu.');
                    break;
                }
            }
            $this->em->clear();
        }

        $this->recorder->note(['translated' => $done, 'failed' => $failed]);
        $io->success(sprintf('%d traduit(s), %d échec(s), %d en attente après ce passage.', $done, $failed, max(0, count($ids) - $done - $failed)));

        return $failed > 0 && $done === 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
