<?php

namespace App\MyLudo;

/**
 * Lit l'export CSV de MyLudo (ma ludothèque) : séparateur « ; », guillemets, virgule décimale,
 * et une première cellule parfois précédée d'une apostrophe (« 'ID »).
 */
final class CsvParser
{
    /**
     * @return list<array{index: int, id: string, title: string, subtitle: string, year: ?int, type: string,
     *                    isExpansion: bool, publisher: string, players: string, duration: string, rating: ?float}>
     *
     * @throws \InvalidArgumentException si le fichier n'a pas la forme d'un export MyLudo
     */
    public function parse(string $csv): array
    {
        $csv = ltrim($csv, "\xEF\xBB\xBF");
        $csv = preg_replace("/^'(?=ID;)/", '', $csv) ?? $csv;

        $stream = fopen('php://memory', 'w+');
        fwrite($stream, $csv);
        rewind($stream);

        $header = fgetcsv($stream, separator: ';', escape: '');
        if ($header === false || $header === [null]) {
            throw new \InvalidArgumentException('Fichier vide.');
        }
        $columns = array_flip(array_map(fn(?string $name) => trim((string) $name), $header));
        if (!isset($columns['Titre'])) {
            throw new \InvalidArgumentException('Ce fichier ne ressemble pas à un export MyLudo : la colonne « Titre » manque.');
        }

        $cell = fn(array $record, string $name): string => isset($columns[$name]) ? trim((string) ($record[$columns[$name]] ?? '')) : '';

        $rows = [];
        while (($record = fgetcsv($stream, separator: ';', escape: '')) !== false) {
            $title = $cell($record, 'Titre');
            if ($title === '') {
                continue;
            }

            $type = mb_strtolower($cell($record, 'Type'));
            $year = (int) $cell($record, 'Édition');
            $rating = (float) str_replace(',', '.', $cell($record, 'Note personnelle'));

            $rows[] = [
                'index'       => count($rows),
                'id'          => $cell($record, 'ID'),
                'title'       => $title,
                'subtitle'    => $cell($record, 'Sous-titre'),
                'year'        => $year >= 1000 && $year <= 2100 ? $year : null,
                'type'        => $type,
                'isExpansion' => str_contains($type, 'expan') || str_contains($type, 'extens'),
                'publisher'   => $cell($record, 'Éditeur(s)'),
                'players'     => $cell($record, 'Joueur(s)'),
                'duration'    => $cell($record, 'Durée'),
                'rating'      => $rating > 0 && $rating <= 10 ? $rating : null,
            ];
        }
        fclose($stream);

        return $rows;
    }
}
