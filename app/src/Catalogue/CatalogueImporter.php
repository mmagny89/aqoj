<?php

declare(strict_types=1);

namespace App\Catalogue;

use App\Catalogue\Import\GameData;
use App\Catalogue\Import\SourceImporterInterface;
use App\Service\JobRunRecorder;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Rapproche une source du catalogue : création ou mise à jour par identifiant
 * BGG, par lots, sans passer par l'unité de travail Doctrine (près de 180 000 lignes).
 *
 * Une fiche déjà enrichie par l'API BGG garde sa note : le CSV ne l'écrase pas.
 */
final class CatalogueImporter
{
    private const BATCH_SIZE = 500;

    /**
     * @param ServiceLocator<SourceImporterInterface> $importers
     */
    public function __construct(
        #[AutowireLocator(SourceImporterInterface::class)]
        private readonly ServiceLocator $importers,
        private readonly Connection $connection,
        private readonly JobRunRecorder $recorder,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getSources(): array
    {
        return array_keys($this->importers->getProvidedServices());
    }

    /**
     * @throws \InvalidArgumentException si la source n'existe pas
     */
    public function import(string $source, \SplFileInfo $file, bool $includeExpansions = false, bool $dryRun = false): ImportReport
    {
        if (!$this->importers->has($source)) {
            throw new \InvalidArgumentException(\sprintf('Source inconnue "%s" (disponibles : %s).', $source, implode(', ', $this->getSources())));
        }

        $read = $created = $updated = $skippedExpansions = 0;
        $batch = [];

        foreach ($this->importers->get($source)->import($file) as $data) {
            ++$read;
            if ($data->isExpansion && !$includeExpansions) {
                ++$skippedExpansions;
                continue;
            }

            // Un doublon dans la source ne doit pas créer deux fiches : le dernier gagne.
            $batch[$data->bggId] = $data;

            if (\count($batch) >= self::BATCH_SIZE) {
                [$batchCreated, $batchUpdated] = $dryRun ? [0, 0] : $this->flushBatch($batch);
                $created += $batchCreated;
                $updated += $batchUpdated;
                $batch = [];

                // Arrêt demandé par un administrateur : les lots déjà écrits sont acquis, le reste est laissé.
                if ($this->recorder->stopRequested()) {
                    $this->recorder->note(['arret_demande' => true]);
                    break;
                }
            }
        }
        [$batchCreated, $batchUpdated] = $dryRun ? [0, 0] : $this->flushBatch($batch);

        $report = new ImportReport($read, $created + $batchCreated, $updated + $batchUpdated, $skippedExpansions);
        $this->recorder->note([
            'source' => $source,
            'fichier' => $file->getFilename(),
            'lues' => $report->read,
            'creees' => $report->created,
            'mises_a_jour' => $report->updated,
            'extensions_ignorees' => $report->skippedExpansions,
            'dry_run' => $dryRun,
        ]);

        return $report;
    }

    /**
     * @param array<string, GameData> $batch
     *
     * @return array{int, int} fiches créées, fiches mises à jour
     */
    private function flushBatch(array $batch): array
    {
        if ([] === $batch) {
            return [0, 0];
        }

        $tuples = implode(', ', array_fill(0, \count($batch), '(?, ?, ?, ?, ?, ?, ?)'));

        $sql = <<<SQL
            INSERT INTO game
                (bgg_id, name, year_published, bgg_rank, rating_bgg, users_rated, is_expansion,
                 source, last_synced_at, created_at, min_players, max_players, playing_time, complexity, categories, mechanics)
            SELECT
                v.bgg_id, v.name, v.year_published::int, v.bgg_rank::int, v.rating_bgg::float,
                v.users_rated::int, v.is_expansion::boolean,
                'bgg_csv', NULL, NOW(), 1, 1, 0, 0.0, '[]'::json, '[]'::json
            FROM (VALUES $tuples)
                AS v(bgg_id, name, year_published, bgg_rank, rating_bgg, users_rated, is_expansion)
            ON CONFLICT (bgg_id) DO UPDATE SET
                name           = EXCLUDED.name,
                year_published = EXCLUDED.year_published,
                bgg_rank       = EXCLUDED.bgg_rank,
                rating_bgg     = CASE WHEN game.source = 'bgg_api' THEN game.rating_bgg ELSE EXCLUDED.rating_bgg END,
                users_rated    = EXCLUDED.users_rated,
                is_expansion   = EXCLUDED.is_expansion
            RETURNING (xmax = 0) AS inserted
        SQL;

        $params = [];
        foreach ($batch as $data) {
            $params[] = $data->bggId;
            $params[] = $data->name;
            $params[] = null === $data->yearPublished ? null : (string) $data->yearPublished;
            $params[] = null === $data->bggRank ? null : (string) $data->bggRank;
            $params[] = null === $data->ratingBgg ? null : (string) $data->ratingBgg;
            $params[] = null === $data->usersRated ? null : (string) $data->usersRated;
            $params[] = $data->isExpansion ? 'true' : 'false';
        }

        $created = 0;
        foreach ($this->connection->fetchFirstColumn($sql, $params) as $inserted) {
            $created += filter_var($inserted, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        return [$created, \count($batch) - $created];
    }
}
