<?php

declare(strict_types=1);

namespace App\Command;

use App\Catalogue\CatalogueImporter;
use App\Catalogue\Import\CsvBggImporter;
use App\Catalogue\Import\InvalidSourceException;
use App\Catalogue\ImportReport;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:catalogue',
    description: 'Importe un fichier source dans le catalogue des jeux',
    help: <<<'HELP'
        Crée les fiches absentes et met à jour les autres par identifiant BGG. Une fiche
        déjà enrichie par l'API BGG garde sa note. Les extensions sont ignorées sans
        --include-expansions. Le dépôt d'un ZIP dans var/imports/bgg/ est repris par
        app:bgg:import-csv, que le planificateur appelle.

        En développement, ajouter --no-debug : le profileur garde sinon chaque
        requête SQL en mémoire et l'import complet dépasse 256 Mo.
        HELP,
)]
final readonly class ImportCatalogueCommand
{
    public function __construct(
        private CatalogueImporter $catalogueImporter,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Chemin du fichier à importer, lisible depuis le conteneur')]
        string $file,
        #[Option(description: 'Source du fichier (bgg...)')]
        string $source = CsvBggImporter::SOURCE,
        #[Option(name: 'include-expansions', description: 'Importer aussi les extensions')]
        bool $includeExpansions = false,
        #[Option(name: 'dry-run', description: 'Lire la source sans rien écrire en base')]
        bool $dryRun = false,
    ): int {
        try {
            $report = $this->catalogueImporter->import($source, new \SplFileInfo($file), $includeExpansions, $dryRun);
        } catch (InvalidSourceException|\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(self::summary($report, $dryRun));

        return Command::SUCCESS;
    }

    public static function summary(ImportReport $report, bool $dryRun): string
    {
        if ($dryRun) {
            return \sprintf('Dry-run : %d ligne(s) exploitable(s), %d extension(s) ignorée(s), rien écrit.', $report->read, $report->skippedExpansions);
        }

        return \sprintf('%d créé(s), %d mis à jour, %d extension(s) ignorée(s).', $report->created, $report->updated, $report->skippedExpansions);
    }
}
