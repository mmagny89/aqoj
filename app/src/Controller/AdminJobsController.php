<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Historique des tâches d'import et de synchronisation (table `job_run`, voir JobRunSubscriber).
 * Lecture seule : le planificateur reste le seul à les lancer.
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminJobsController extends AbstractController
{
    /** Une exécution « en cours » depuis plus longtemps est celle d'un worker coupé net. */
    private const INTERRUPTED_AFTER = '12 hours';

    private const SELECT = <<<SQL
        SELECT id, command, arguments, started_at, finished_at, exit_code, metrics, error,
               EXTRACT(EPOCH FROM (finished_at - started_at))::int AS duration,
               CASE WHEN status = 'running' AND started_at < NOW() - INTERVAL '12 hours' THEN 'interrupted' ELSE status END AS status
        FROM job_run
        SQL;

    #[Route('/api/admin/jobs', name: 'api_admin_jobs', methods: ['GET'])]
    public function list(Request $request, Connection $conn): JsonResponse
    {
        $limit = max(1, min(200, $request->query->getInt('limit', 50)));
        $command = $request->query->getString('command');
        $status = $request->query->getString('status');

        $where = [];
        $params = [];
        if ('' !== $command) {
            $where[] = 'command = ?';
            $params[] = $command;
        }
        if ('' !== $status) {
            $where[] = "(CASE WHEN status = 'running' AND started_at < NOW() - INTERVAL '".self::INTERRUPTED_AFTER."' THEN 'interrupted' ELSE status END) = ?";
            $params[] = $status;
        }

        $sql = 'SELECT * FROM ('.self::SELECT.') j'.([] === $where ? '' : ' WHERE '.implode(' AND ', $where)).' ORDER BY started_at DESC, id DESC LIMIT '.$limit;
        $runs = array_map($this->present(...), $conn->fetchAllAssociative($sql, $params));

        // Dernière exécution de chaque tâche, indépendamment des filtres : l'état d'un coup d'œil.
        $last = $conn->fetchAllAssociative('SELECT DISTINCT ON (command) * FROM ('.self::SELECT.') j ORDER BY command, started_at DESC, id DESC');

        return $this->json([
            'runs' => $runs,
            'last' => array_map($this->present(...), $last),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'command' => $row['command'],
            'arguments' => $row['arguments'],
            'status' => $row['status'],
            'startedAt' => $this->iso($row['started_at']),
            'finishedAt' => $this->iso($row['finished_at']),
            'durationSeconds' => null === $row['duration'] ? null : (int) $row['duration'],
            'exitCode' => null === $row['exit_code'] ? null : (int) $row['exit_code'],
            'metrics' => json_decode((string) $row['metrics'], true) ?: new \stdClass(),
            'error' => $row['error'],
        ];
    }

    private function iso(?string $date): ?string
    {
        return null === $date ? null : (new \DateTimeImmutable($date))->format('c');
    }
}
