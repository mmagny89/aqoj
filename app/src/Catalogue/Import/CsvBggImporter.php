<?php

declare(strict_types=1);

namespace App\Catalogue\Import;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Import du CSV de classement publié par BoardGameGeek (boardgames_ranks.csv),
 * sans aucun appel à l'API.
 */
#[AsTaggedItem(index: self::SOURCE)]
final class CsvBggImporter implements SourceImporterInterface
{
    /** Identifiant passé à `app:import:catalogue --source`. */
    public const SOURCE = 'bgg';

    private const REQUIRED_COLUMNS = ['id', 'name', 'yearpublished', 'rank', 'bayesaverage', 'usersrated', 'is_expansion'];

    public function import(\SplFileInfo $file): iterable
    {
        try {
            $csv = $file->openFile('r');
        } catch (\RuntimeException $exception) {
            throw new InvalidSourceException(\sprintf('Fichier illisible : %s.', $file->getPathname()), 0, $exception);
        }
        $csv->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::READ_AHEAD | \SplFileObject::DROP_NEW_LINE);
        $csv->setCsvControl(',', '"', '');

        $header = $csv->current();
        if (!\is_array($header)) {
            throw new InvalidSourceException('Fichier CSV vide ou invalide.');
        }
        $header = array_map(static fn (?string $column): string => trim((string) $column, "\u{FEFF} "), $header);
        $missing = array_diff(self::REQUIRED_COLUMNS, $header);
        if ([] !== $missing) {
            throw new InvalidSourceException(\sprintf('Colonnes manquantes dans le CSV : %s.', implode(', ', $missing)));
        }
        $csv->next();

        while ($csv->valid()) {
            $row = $csv->current();
            $csv->next();

            if (!\is_array($row) || \count($row) !== \count($header)) {
                continue;
            }

            $game = $this->toGameData(array_map(static fn (?string $value): string => trim((string) $value), array_combine($header, $row)));
            if (null !== $game) {
                yield $game;
            }
        }
    }

    /**
     * @param array<string, string> $row
     */
    private function toGameData(array $row): ?GameData
    {
        if ('' === $row['id'] || '' === $row['name']) {
            return null;
        }

        return new GameData(
            bggId: $row['id'],
            name: mb_substr($row['name'], 0, 255),
            yearPublished: '' !== $row['yearpublished'] ? (int) $row['yearpublished'] : null,
            bggRank: '' !== $row['rank'] ? (int) $row['rank'] : null,
            ratingBgg: is_numeric($row['bayesaverage']) && (float) $row['bayesaverage'] > 0 ? (float) $row['bayesaverage'] : null,
            usersRated: '' !== $row['usersrated'] ? (int) $row['usersrated'] : null,
            isExpansion: '1' === $row['is_expansion'],
        );
    }
}
