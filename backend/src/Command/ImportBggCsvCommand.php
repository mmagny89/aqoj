<?php

namespace App\Command;

use App\Service\BggCsvImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:bgg:import-csv',
    description: 'Importe le catalogue BGG depuis un ZIP déposé dans var/imports/bgg/',
)]
class ImportBggCsvCommand extends Command
{
    private const IMPORT_DIR = '/var/imports/bgg';
    private const ARCHIVE_DIR = '/var/imports/bgg/processed';
    private const CSV_FILENAME = 'boardgames_ranks.csv';

    public function __construct(
        private readonly BggCsvImportService $importService,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('include-expansions', null, InputOption::VALUE_NONE, 'Importer aussi les extensions (is_expansion=1)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simuler sans écrire en base');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Import BGG CSV');

        $importDir = $this->projectDir . self::IMPORT_DIR;
        $archiveDir = $this->projectDir . self::ARCHIVE_DIR;
        $includeExpansions = (bool) $input->getOption('include-expansions');
        $dryRun = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $io->note('Mode dry-run : aucune écriture en base.');
        }

        $zipPath = $this->findLatestZip($importDir);
        if ($zipPath === null) {
            $io->warning("Aucun fichier ZIP trouvé dans {$importDir}");
            return Command::SUCCESS;
        }

        $io->text("Fichier trouvé : " . basename($zipPath));

        $csvPath = $this->extractCsv($zipPath);
        if ($csvPath === null) {
            $io->error("Impossible d'extraire " . self::CSV_FILENAME . " du ZIP.");
            return Command::FAILURE;
        }

        try {
            if ($dryRun) {
                $io->success('Dry-run OK — fichier CSV valide, aucune donnée écrite.');
                return Command::SUCCESS;
            }

            $io->text('Import en cours (cela peut prendre quelques minutes)…');
            $stats = $this->importService->importFromCsvFile($csvPath, $includeExpansions);

            $io->success(sprintf(
                'Import terminé : %d jeux traités, %d ignorés (extensions), %d erreurs.',
                $stats['imported'],
                $stats['skipped'],
                $stats['errors'],
            ));

            $this->archiveZip($zipPath, $archiveDir);
            $io->text('ZIP archivé dans ' . self::ARCHIVE_DIR);
        } finally {
            @unlink($csvPath);
            $tmpDir = dirname($csvPath);
            if (str_contains($tmpDir, 'bgg_extract_')) {
                array_map('unlink', glob($tmpDir . '/*') ?: []);
                @rmdir($tmpDir);
            }
        }

        return Command::SUCCESS;
    }

    private function findLatestZip(string $dir): ?string
    {
        if (!is_dir($dir)) {
            return null;
        }

        $zips = glob($dir . '/*.zip') ?: [];
        if (empty($zips)) {
            return null;
        }

        usort($zips, static fn(string $a, string $b) => filemtime($b) <=> filemtime($a));

        return $zips[0];
    }

    private function extractCsv(string $zipPath): ?string
    {
        $tmpDir = sys_get_temp_dir() . '/' . uniqid('bgg_extract_', true);
        mkdir($tmpDir, 0700, true);

        $zipPath = escapeshellarg($zipPath);
        $tmpDirArg = escapeshellarg($tmpDir);
        exec("unzip -q {$zipPath} " . escapeshellarg(self::CSV_FILENAME) . " -d {$tmpDirArg} 2>&1", $output, $exitCode);

        $csvPath = $tmpDir . '/' . self::CSV_FILENAME;
        if ($exitCode === 0 && file_exists($csvPath)) {
            return $csvPath;
        }

        // Le CSV peut être dans un sous-dossier du ZIP
        exec("unzip -q {$zipPath} -d {$tmpDirArg} 2>&1", $output, $exitCode);
        $found = glob($tmpDir . '/**/' . self::CSV_FILENAME) ?: glob($tmpDir . '/' . self::CSV_FILENAME);
        if (!empty($found)) {
            return $found[0];
        }

        return null;
    }

    private function archiveZip(string $zipPath, string $archiveDir): void
    {
        if (!is_dir($archiveDir)) {
            mkdir($archiveDir, 0750, true);
        }

        $dest = $archiveDir . '/' . date('Y-m-d_His_') . basename($zipPath);
        rename($zipPath, $dest);
    }
}
