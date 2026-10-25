<?php

namespace App\MyLudo;

/**
 * Lit l'export CSV des parties de MyLudo : séparateur « ; », guillemets, BOM et apostrophe devant « ID ».
 * La colonne « ID » est celle du JEU (répétée à chaque partie), pas de la partie : la clé d'import d'une partie
 * se bâtit donc sur son contenu (jeu, date, lieu, joueurs et scores, commentaire), qui change d'une partie à l'autre.
 */
final class PlaysParser
{
    /**
     * @return list<array{id: string, title: string, subtitle: string, year: ?int, type: string, isExpansion: bool,
     *                    playedAt: ?string, place: string, playersCount: int, comment: string, rating: ?int,
     *                    excluded: bool, key: string}>
     *
     * @throws \InvalidArgumentException si le fichier n'a pas la forme d'un export de parties MyLudo
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
        foreach (['Titre', 'Date', 'Joueurs'] as $required) {
            if (!isset($columns[$required])) {
                throw new \InvalidArgumentException("Ce fichier ne ressemble pas à un export de parties MyLudo : la colonne « $required » manque.");
            }
        }

        $cell = fn(array $record, string $name): string => isset($columns[$name]) ? trim((string) ($record[$columns[$name]] ?? '')) : '';

        $plays = [];
        $seen = [];
        while (($record = fgetcsv($stream, separator: ';', escape: '')) !== false) {
            $title = $cell($record, 'Titre');
            if ($title === '') {
                continue;
            }

            $type = mb_strtolower($cell($record, 'Type'));
            $year = (int) $cell($record, 'Édition');
            $date = $cell($record, 'Date');
            $validDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4));

            $names = $cell($record, 'Joueurs');
            $count = (int) $cell($record, 'NB Joueurs');
            if ($count < 1) {
                // « Moi (39), Mymy (41) » : à défaut de total, on compte les participants.
                $count = $names === '' ? 0 : preg_match_all('/\([^)]*\)\s*(?:,|$)/', $names);
            }

            $personal = (float) str_replace(',', '.', $cell($record, 'Note personnelle'));

            // Deux parties identiques (même jeu, jour, lieu, scores, durée) sont deux parties : la deuxième porte « #2 ».
            // La première garde la clé sans suffixe, donc un fichier déjà importé ne bouge pas.
            $base = implode('|', [$cell($record, 'ID'), $date, $cell($record, 'Lieu'), $names, $cell($record, 'Durée'), $cell($record, 'Commentaire')]);
            $seen[$base] = ($seen[$base] ?? 0) + 1;

            $plays[] = [
                'id'           => $cell($record, 'ID'),
                'title'        => $title,
                'subtitle'     => $cell($record, 'Sous-titre'),
                'year'         => $year >= 1000 && $year <= 2100 ? $year : null,
                'type'         => $type,
                'isExpansion'  => str_contains($type, 'expan') || str_contains($type, 'extens'),
                'playedAt'     => $validDate ? $date : null,
                'place'        => mb_substr($cell($record, 'Lieu'), 0, 100),
                'playersCount' => max(1, $count),
                'comment'      => $cell($record, 'Commentaire'),
                'rating'       => self::rating($personal),
                'excluded'     => mb_strtolower($cell($record, 'Exclue')) === 'oui',
                'key'          => md5($seen[$base] === 1 ? $base : $base . '#' . $seen[$base]),
            ];
        }
        fclose($stream);

        return $plays;
    }

    /**
     * La note de MyLudo est celle du jeu, sur 10, répétée à chaque partie (0 = aucune). Ici : 7 et plus → aimé (5),
     * de 1 à 4 → pas aimé (1), entre les deux ou sans note → pas d'avis.
     */
    public static function rating(float $personal): ?int
    {
        return match (true) {
            $personal >= 7 && $personal <= 10 => 5,
            $personal > 0 && $personal <= 4   => 1,
            default                           => null,
        };
    }
}
