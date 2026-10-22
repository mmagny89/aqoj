<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

class BggCsvImportService
{
    private const BATCH_SIZE = 500;

    public function __construct(private readonly Connection $connection) {}

    /**
     * @return array{imported: int, skipped: int, errors: int}
     */
    public function importFromCsvFile(string $csvPath, bool $includeExpansions = false): array
    {
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Impossible d'ouvrir le fichier CSV : {$csvPath}");
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            throw new \RuntimeException('Fichier CSV vide ou invalide.');
        }

        $headerMap = array_flip($headers);
        $this->assertRequiredColumns($headerMap);

        $stats = ['imported' => 0, 'skipped' => 0, 'errors' => 0];
        $batch = [];
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($headers)) {
                $stats['errors']++;
                continue;
            }

            $isExpansion = (bool) ($row[$headerMap['is_expansion']] ?? 0);
            if ($isExpansion && !$includeExpansions) {
                $stats['skipped']++;
                continue;
            }

            $bggId = trim($row[$headerMap['id']] ?? '');
            $name = trim($row[$headerMap['name']] ?? '');
            if ($bggId === '' || $name === '') {
                $stats['errors']++;
                continue;
            }

            $rank = $row[$headerMap['rank']] ?? '';
            $bayesaverage = $row[$headerMap['bayesaverage']] ?? '';
            $usersrated = $row[$headerMap['usersrated']] ?? '';
            $yearpublished = $row[$headerMap['yearpublished']] ?? '';

            $batch[] = [
                'bgg_id' => $bggId,
                'name' => mb_substr($name, 0, 255),
                'year_published' => $yearpublished !== '' ? (int) $yearpublished : null,
                'bgg_rank' => $rank !== '' ? (int) $rank : null,
                'rating_bgg' => $bayesaverage !== '' && (float) $bayesaverage > 0 ? (float) $bayesaverage : null,
                'users_rated' => $usersrated !== '' ? (int) $usersrated : null,
                'is_expansion' => $isExpansion,
                'last_synced_at' => $now,
            ];

            if (count($batch) >= self::BATCH_SIZE) {
                $stats['imported'] += $this->flushBatch($batch);
                $batch = [];
            }
        }

        fclose($handle);

        if (!empty($batch)) {
            $stats['imported'] += $this->flushBatch($batch);
        }

        return $stats;
    }

    /**
     * @param list<array<string, mixed>> $batch
     */
    private function flushBatch(array $batch): int
    {
        if (empty($batch)) {
            return 0;
        }

        // One placeholder tuple per row: (?, ?, ?, ?, ?, ?, ?)
        $tuples = implode(', ', array_fill(0, count($batch), '(?, ?, ?, ?, ?, ?, ?)'));

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
        SQL;

        $params = [];
        foreach ($batch as $row) {
            $params[] = $row['bgg_id'];
            $params[] = $row['name'];
            $params[] = $row['year_published'] !== null ? (string) $row['year_published'] : null;
            $params[] = $row['bgg_rank'] !== null ? (string) $row['bgg_rank'] : null;
            $params[] = $row['rating_bgg'] !== null ? (string) $row['rating_bgg'] : null;
            $params[] = $row['users_rated'] !== null ? (string) $row['users_rated'] : null;
            $params[] = $row['is_expansion'] ? 'true' : 'false';
        }

        return (int) $this->connection->executeStatement($sql, $params);
    }

    private function assertRequiredColumns(array $headerMap): void
    {
        $required = ['id', 'name', 'yearpublished', 'rank', 'bayesaverage', 'usersrated', 'is_expansion'];
        $missing = array_diff($required, array_keys($headerMap));
        if (!empty($missing)) {
            throw new \RuntimeException('Colonnes manquantes dans le CSV : ' . implode(', ', $missing));
        }
    }
}
