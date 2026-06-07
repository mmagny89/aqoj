<?php

namespace App\Command;

use App\Service\MechanicFamilyResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:recompute-mechanic-families',
    description: 'Recalcule les familles Engelstein pour tous les jeux en base à partir des mappings actifs.',
)]
class RecomputeMechanicFamiliesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MechanicFamilyResolver $familyResolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('batch-size', null, InputOption::VALUE_OPTIONAL, 'Nombre de jeux par batch', 200);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $batchSize = (int) $input->getOption('batch-size');

        $conn = $this->em->getConnection();
        $total = (int) $conn->fetchOne('SELECT COUNT(*) FROM game');

        if ($total === 0) {
            $io->info('Aucun jeu en base.');
            return Command::SUCCESS;
        }

        $familyMap  = $this->familyResolver->getFamilyMap();
        $engineKeys = array_flip(MechanicFamilyResolver::ENGINE_FAMILIES);
        $io->progressStart($total);
        $updated = 0;
        $offset  = 0;

        while (true) {
            $rows = $conn->fetchAllAssociative(
                'SELECT id, mechanics FROM game ORDER BY id ASC LIMIT ? OFFSET ?',
                [$batchSize, $offset]
            );

            if (empty($rows)) {
                break;
            }

            $casesFamilies  = [];
            $casesEngines   = [];
            $paramsFamilies = [];
            $paramsEngines  = [];
            $ids            = [];

            foreach ($rows as $row) {
                $mechanics    = json_decode($row['mechanics'], true) ?? [];
                $families     = [];
                $engineCounts = [];

                foreach ($mechanics as $mechanic) {
                    if (!isset($familyMap[$mechanic])) {
                        continue;
                    }
                    $family = $familyMap[$mechanic];
                    $families[$family] = true;
                    if (isset($engineKeys[$family])) {
                        $engineCounts[$family] = ($engineCounts[$family] ?? 0) + 1;
                    }
                }

                // Moteur le plus représenté en premier
                arsort($engineCounts);

                $casesFamilies[]  = 'WHEN id = ? THEN ?::jsonb';
                $paramsFamilies[] = $row['id'];
                $paramsFamilies[] = json_encode(array_values(array_keys($families)));

                $casesEngines[]  = 'WHEN id = ? THEN ?::jsonb';
                $paramsEngines[] = $row['id'];
                $paramsEngines[] = json_encode(array_values(array_keys($engineCounts)));

                $ids[] = $row['id'];
                $updated++;
            }

            $placeholders = implode(', ', array_fill(0, count($ids), '?'));
            $conn->executeStatement(
                'UPDATE game SET
                    mechanic_families = CASE ' . implode(' ', $casesFamilies) . ' END,
                    detected_engines  = CASE ' . implode(' ', $casesEngines) . ' END
                WHERE id IN (' . $placeholders . ')',
                array_merge($paramsFamilies, $paramsEngines, $ids)
            );

            $io->progressAdvance(count($rows));

            $offset += $batchSize;
        }

        $io->progressFinish();
        $io->success("Familles recalculées pour {$updated} jeux.");

        return Command::SUCCESS;
    }
}
