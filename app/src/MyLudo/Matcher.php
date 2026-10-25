<?php

namespace App\MyLudo;

use Doctrine\DBAL\Connection;

/**
 * Rapproche les lignes d'un export MyLudo du catalogue (jeux BGG). MyLudo ne fournit aucun identifiant BGG :
 * on compare les titres (sans accents, casse ni ponctuation) à `name` (titre français quand il est connu)
 * et à `name_original` (titre BGG), puis l'année d'édition départage.
 *
 * Un rapprochement n'est automatique que s'il est sans doute possible ; sinon l'utilisateur tranche.
 *   - matched   : un seul candidat, ou un seul dont l'année est celle de l'édition ;
 *   - ambiguous : plusieurs candidats plausibles, du plus proche au plus lointain ;
 *   - unmatched : aucun candidat.
 */
final class Matcher
{
    private const MAX_CANDIDATES = 8;

    private ?\Transliterator $transliterator = null;

    public function __construct(private readonly Connection $conn) {}

    /**
     * @param list<array<string, mixed>> $rows lignes de {@see CsvParser}
     *
     * @return list<array<string, mixed>> les mêmes lignes, avec `status` et `game` ou `candidates`
     */
    public function match(array $rows): array
    {
        $keysByRow = [];
        $allKeys   = [];
        foreach ($rows as $row) {
            $keys = array_values(array_filter(array_unique([
                $this->key($row['title'] . ' ' . $row['subtitle']),
                $this->key($row['title']),
            ])));
            $keysByRow[$row['index']] = $keys;
            array_push($allKeys, ...$keys);
        }

        $byKey = $this->fetchByKey(array_values(array_unique($allKeys)));

        $result = [];
        foreach ($rows as $row) {
            $result[] = $row + $this->classify($row, $keysByRow[$row['index']], $byKey);
        }

        return $result;
    }

    /**
     * @param list<string>                     $keys
     * @param array<string, list<array<string, mixed>>> $byKey
     *
     * @return array<string, mixed>
     */
    private function classify(array $row, array $keys, array $byKey): array
    {
        $candidates = [];
        // Le titre complet (avec sous-titre) prime ; le titre seul ne sert que s'il n'a rien donné.
        foreach ($keys as $key) {
            $found = array_filter($byKey[$key] ?? [], fn(array $g) => $g['isExpansion'] === $row['isExpansion']);
            if ($found !== []) {
                $candidates = $found;
                break;
            }
        }

        $edition = $row['year'];
        // Une édition ne peut pas précéder le jeu : un candidat paru plus tard n'est pas plausible.
        $plausible = array_values(array_filter(
            $candidates,
            fn(array $g) => $edition === null || $g['yearPublished'] === null || $g['yearPublished'] <= $edition + 1,
        ));
        if ($plausible === []) {
            return ['status' => 'unmatched'];
        }

        usort($plausible, function (array $a, array $b) use ($edition): int {
            $distance = fn(array $g) => $edition !== null && $g['yearPublished'] !== null ? abs($edition - $g['yearPublished']) : 999;

            return [$distance($a), -($a['usersRated'] ?? 0)] <=> [$distance($b), -($b['usersRated'] ?? 0)];
        });

        $sameYear = array_values(array_filter($plausible, fn(array $g) => $edition !== null && $g['yearPublished'] === $edition));
        if (count($plausible) === 1) {
            return ['status' => 'matched', 'game' => $plausible[0]];
        }
        if (count($sameYear) === 1) {
            return ['status' => 'matched', 'game' => $sameYear[0]];
        }

        return ['status' => 'ambiguous', 'candidates' => array_slice($plausible, 0, self::MAX_CANDIDATES)];
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, list<array<string, mixed>>> clé de titre => jeux
     */
    private function fetchByKey(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        // Les clés ne contiennent que [a-z0-9] : on les passe jointes par une virgule, en un seul paramètre.
        $joined = implode(',', $keys);
        $rows = $this->conn->fetchAllAssociative(
            "SELECT * FROM (
                 SELECT g.id, g.name, g.name_original, g.year_published, g.rating_bgg, g.users_rated, g.is_expansion, g.image_url,
                        regexp_replace(unaccent(lower(g.name)), '[^a-z0-9]+', '', 'g') AS name_key,
                        regexp_replace(unaccent(lower(COALESCE(g.name_original, ''))), '[^a-z0-9]+', '', 'g') AS original_key
                 FROM game g
             ) s
             WHERE s.name_key = ANY(string_to_array(?, ',')) OR s.original_key = ANY(string_to_array(?, ','))",
            [$joined, $joined],
        );

        $wanted = array_flip($keys);
        $byKey  = [];
        foreach ($rows as $r) {
            $game = [
                'id'            => (int) $r['id'],
                'name'          => $r['name'],
                'nameOriginal'  => $r['name_original'],
                'yearPublished' => $r['year_published'] !== null && (int) $r['year_published'] > 0 ? (int) $r['year_published'] : null,
                'ratingBgg'     => $r['rating_bgg'] !== null ? (float) $r['rating_bgg'] : null,
                'usersRated'    => $r['users_rated'] !== null ? (int) $r['users_rated'] : null,
                'isExpansion'   => (bool) $r['is_expansion'],
                'imageUrl'      => $r['image_url'],
            ];
            foreach ([$r['name_key'], $r['original_key']] as $key) {
                if (isset($wanted[$key])) {
                    $byKey[$key][$game['id']] = $game;
                }
            }
        }

        return array_map('array_values', $byKey);
    }

    /** Même normalisation que le SQL : sans accents, en minuscules, lettres et chiffres seulement. */
    private function key(string $title): string
    {
        $this->transliterator ??= \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
        $ascii = $this->transliterator?->transliterate($title) ?? mb_strtolower($title);

        return preg_replace('/[^a-z0-9]+/', '', $ascii) ?? '';
    }
}
