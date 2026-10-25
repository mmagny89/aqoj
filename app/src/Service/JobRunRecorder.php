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

    /** Dernière lecture de la demande d'arrêt : on n'interroge pas la base à chaque jeu traité. */
    private float $stopCheckedAt = 0.0;

    private bool $stopRequested = false;

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
        $this->stopRequested = false;
        $this->stopCheckedAt = 0.0;

        // Le nom de la session la désigne à l'administration : « Forcer l'arrêt » termine cette connexion-là, ce qui
        // fait tomber la tâche (et son verrou BGG) même si elle ne regarde plus la demande d'arrêt. Il vaut pour la
        // seule exécution en cours : reset() l'efface.
        $this->connection->fetchOne("SELECT set_config('application_name', ?, false)", [self::sessionName($this->currentId)]);
    }

    /** Nom de la session PostgreSQL d'une exécution suivie. */
    public static function sessionName(int $runId): string
    {
        return 'aqoj-job-'.$runId;
    }

    /**
     * Un administrateur a-t-il demandé l'arrêt de cette exécution ? À consulter entre deux lots : la tâche rend alors
     * la main proprement, après avoir enregistré ce qu'elle a fait. Sans effet hors d'une exécution suivie.
     */
    public function stopRequested(): bool
    {
        if (null === $this->currentId) {
            return false;
        }
        if ($this->stopRequested) {
            return true;
        }

        // Au plus une lecture par seconde.
        $now = microtime(true);
        if ($now - $this->stopCheckedAt < 1.0) {
            return false;
        }
        $this->stopCheckedAt = $now;

        try {
            $this->stopRequested = (bool) $this->connection->fetchOne(
                'SELECT stop_requested_at IS NOT NULL FROM job_run WHERE id = ?',
                [$this->currentId],
            );
        } catch (\Throwable) {
            // Le journal ne doit jamais faire échouer la tâche.
        }

        return $this->stopRequested;
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
        // Une tâche qui s'est arrêtée à la demande d'un administrateur n'est ni un succès ni un échec.
        if ('success' === $status && $this->stopRequested) {
            $status = 'cancelled';
        }

        $this->connection->executeStatement(
            'UPDATE job_run SET finished_at = ?, status = ?, exit_code = ?, metrics = ?::jsonb, error = ? WHERE id = ? AND status = \'running\'',
            [new \DateTimeImmutable(), $status, $exitCode, json_encode([] === $this->metrics ? new \stdClass() : $this->metrics, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), null === $error ? null : mb_substr($error, 0, 2000), $this->currentId],
            [Types::DATETIME_IMMUTABLE, Types::STRING, Types::INTEGER, Types::STRING, Types::STRING, Types::INTEGER],
        );
        $this->reset();
    }

    public function reset(): void
    {
        if (null !== $this->currentId) {
            try {
                $this->connection->fetchOne("SELECT set_config('application_name', '', false)");
            } catch (\Throwable) {
                // Connexion déjà perdue (arrêt forcé) : rien à effacer.
            }
        }
        $this->currentId = null;
        $this->metrics = [];
        $this->stopRequested = false;
    }
}
