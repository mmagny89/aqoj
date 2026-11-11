<?php

namespace App\Controller;

use App\MyLudo\Matcher;
use App\MyLudo\PlaysParser;
use App\Service\UserPreferenceService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Import des parties depuis l'export CSV de MyLudo, en deux temps comme celui de la ludothèque : l'analyse ne
 * touche à rien et rapproche chaque JEU du fichier du catalogue ; le joueur confirme, puis seules les parties
 * des jeux confirmés entrent dans « Mes parties ». Rejouer le même fichier n'ajoute rien (clé d'import).
 */
class MyLudoPlaysController extends AbstractController
{
    use CurrentUser;

    private const MAX_CSV_BYTES = 3_000_000;
    private const MAX_PLAYS = 10_000;
    private const CHUNK = 500;

    #[Route('/api/myludo/plays/analyze', name: 'api_myludo_plays_analyze', methods: ['POST'])]
    public function analyze(Request $request, PlaysParser $parser, Matcher $matcher, Connection $conn): JsonResponse
    {
        $plays = $this->readPlays($request, $parser, $error);
        if ($plays === null) {
            return $error;
        }
        $user = $this->appUser();

        // Un « jeu » du fichier = un identifiant MyLudo ; ses parties se comptent.
        $games = [];
        foreach ($plays as $play) {
            if ($play['excluded']) {
                continue;
            }
            $games[$play['id']] ??= $play + ['index' => count($games), 'plays' => 0];
            $games[$play['id']]['plays']++;
        }

        $already = array_flip($conn->fetchFirstColumn(
            'SELECT import_key FROM game_session WHERE user_id = ? AND import_key IS NOT NULL',
            [$user->getId()],
        ));
        $alreadyByGame = [];
        foreach ($plays as $play) {
            if (!$play['excluded'] && isset($already[$play['key']])) {
                $alreadyByGame[$play['id']] = ($alreadyByGame[$play['id']] ?? 0) + 1;
            }
        }

        $rows = array_map(
            fn(array $row) => $row + ['already' => $alreadyByGame[$row['id']] ?? 0],
            $matcher->match(array_values($games)),
        );
        $count = fn(string $status) => count(array_filter($rows, fn(array $r) => $r['status'] === $status));

        return $this->json([
            'rows'    => $rows,
            'summary' => [
                'plays'     => count($plays) - count(array_filter($plays, fn(array $p) => $p['excluded'])),
                'excluded'  => count(array_filter($plays, fn(array $p) => $p['excluded'])),
                'already'   => array_sum($alreadyByGame),
                'total'     => count($rows),
                'matched'   => $count('matched'),
                'ambiguous' => $count('ambiguous'),
                'unmatched' => $count('unmatched'),
            ],
        ]);
    }

    /** `{csv, mapping: {<id MyLudo du jeu>: <id du jeu du catalogue>}}` : les parties des jeux absents du mapping restent de côté. */
    #[Route('/api/myludo/plays/import', name: 'api_myludo_plays_import', methods: ['POST'])]
    public function import(Request $request, PlaysParser $parser, Connection $conn, UserPreferenceService $prefService): JsonResponse
    {
        $plays = $this->readPlays($request, $parser, $error);
        if ($plays === null) {
            return $error;
        }
        $user = $this->appUser();

        $mapping = json_decode($request->getContent(), true)['mapping'] ?? null;
        if (!is_array($mapping) || $mapping === []) {
            return $this->json(['error' => 'Aucun jeu à importer.'], 400);
        }
        $gameIds = [];
        foreach ($mapping as $myludoId => $gameId) {
            if (!is_int($gameId) || $gameId < 1) {
                return $this->json(['error' => 'Jeu invalide.'], 400);
            }
            $gameIds[$gameId] = true;
        }
        $known = $conn->fetchFirstColumn(
            'SELECT id FROM game WHERE id IN (' . implode(',', array_fill(0, count($gameIds), '?')) . ')',
            array_keys($gameIds),
        );
        if (count($known) !== count($gameIds)) {
            return $this->json(['error' => 'Un des jeux n\'existe pas dans le catalogue.'], 422);
        }

        $rows = [];
        $left = $excluded = $invalid = 0;
        foreach ($plays as $play) {
            if ($play['excluded']) {
                $excluded++;
            } elseif (!isset($mapping[$play['id']])) {
                $left++;
            } elseif ($play['playedAt'] === null) {
                $invalid++;
            } else {
                $rows[] = $play + ['gameId' => $mapping[$play['id']]];
            }
        }

        $imported = 0;
        $conn->beginTransaction();
        try {
            foreach (array_chunk($rows, self::CHUNK) as $chunk) {
                $values = [];
                $params = [];
                foreach ($chunk as $row) {
                    $values[] = '(?, ?, ?, ?, ?, ?, ?, ?)';
                    // Midi : une partie n'a ici qu'une date, et midi ne change pas de jour d'un fuseau à l'autre.
                    array_push($params, $user->getId(), $row['gameId'], $row['playersCount'], $row['rating'], $row['comment'] ?: null, $row['playedAt'] . ' 12:00:00', $row['place'] ?: null, $row['key']);
                }
                $imported += $conn->executeStatement(
                    'INSERT INTO game_session (user_id, game_id, players_count, rating, note, played_at, context, import_key) VALUES '
                    . implode(', ', $values) . ' ON CONFLICT (user_id, import_key) WHERE import_key IS NOT NULL DO NOTHING',
                    $params,
                );
            }
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        if ($imported > 0) {
            $prefService->recompute($user);
        }

        return $this->json([
            'imported'   => $imported,
            'duplicates' => count($rows) - $imported,
            'left'       => $left,
            'excluded'   => $excluded,
            'invalid'    => $invalid,
        ]);
    }

    /** @return list<array<string, mixed>>|null null avec `$error` renseigné si le fichier est refusé */
    private function readPlays(Request $request, PlaysParser $parser, ?JsonResponse &$error): ?array
    {
        $csv = json_decode($request->getContent(), true)['csv'] ?? null;
        if (!is_string($csv) || trim($csv) === '') {
            $error = $this->json(['error' => 'Déposez le fichier CSV des parties exporté de MyLudo.'], 400);

            return null;
        }
        if (strlen($csv) > self::MAX_CSV_BYTES) {
            $error = $this->json(['error' => 'Fichier trop volumineux (3 Mo au plus).'], 413);

            return null;
        }

        try {
            $plays = $parser->parse($csv);
        } catch (\InvalidArgumentException $e) {
            $error = $this->json(['error' => $e->getMessage()], 422);

            return null;
        }
        if ($plays === []) {
            $error = $this->json(['error' => 'Aucune partie dans ce fichier.'], 422);

            return null;
        }
        if (count($plays) > self::MAX_PLAYS) {
            $error = $this->json(['error' => 'Trop de parties (' . self::MAX_PLAYS . ' au plus).'], 413);

            return null;
        }

        return $plays;
    }
}
