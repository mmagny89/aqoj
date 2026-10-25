<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Journal des tâches (table `job_run`), écrit en SQL direct : les commandes vident
 * l'unité de travail Doctrine à chaque lot, ce qui détacherait une entité de suivi.
 *
 * Le worker enchaîne les commandes dans un même processus : l'exécution en cours
 * est oubliée dès qu'elle se termine.
 */
final class JobRunRecorder implements ResetInterface
{
    private ?int $currentId = null;

    /** @var array<string, mixed> */
    private array $metrics = [];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function start(string $command, string $arguments): void
    {
        $this->metrics = [];
        $this->currentId = (int) $this->connection->fetchOne(
            'INSERT INTO job_run (command, arguments, started_at) VALUES (?, ?, ?) RETURNING id',
            [$command, mb_substr($arguments, 0, 255), new \DateTimeImmutable()],
            [Types::STRING, Types::STRING, Types::DATETIME_IMMUTABLE],
        );
    }

    /**
     * Ajoute des chiffres au bilan de l'exécution en cours ; sans effet hors d'une exécution suivie.
     *
     * @param array<string, scalar|null> $metrics
     */
    public function note(array $metrics): void
    {
        if (null === $this->currentId) {
            return;
        }

        $this->metrics = array_replace($this->metrics, $metrics);
    }

    public function finish(string $status, ?int $exitCode, ?string $error = null): void
    {
        if (null === $this->currentId) {
            return;
        }

        // Un passage sauté (verrou BGG pris, rien à faire) se distingue d'un vrai succès.
        if ('success' === $status && isset($this->metrics['skipped'])) {
            $status = 'skipped';
        }

        $this->connection->executeStatement(
            'UPDATE job_run SET finished_at = ?, status = ?, exit_code = ?, metrics = ?::jsonb, error = ? WHERE id = ?',
            [new \DateTimeImmutable(), $status, $exitCode, json_encode([] === $this->metrics ? new \stdClass() : $this->metrics, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), null === $error ? null : mb_substr($error, 0, 2000), $this->currentId],
            [Types::DATETIME_IMMUTABLE, Types::STRING, Types::INTEGER, Types::STRING, Types::STRING, Types::INTEGER],
        );
        $this->reset();
    }

    public function reset(): void
    {
        $this->currentId = null;
        $this->metrics = [];
    }
}
