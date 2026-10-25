<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Arrêt des tâches par l'administration. Le site web et les tâches vivent dans des conteneurs différents :
 * on ne peut donc pas envoyer de signal au processus. Deux niveaux, tous deux passant par la base.
 *
 *  - demande d'arrêt : un drapeau que la tâche lit entre deux lots (JobRunRecorder::stopRequested) ; elle termine
 *    le lot en cours, l'enregistre, et rend la main. Rien n'est perdu.
 *  - arrêt forcé : la session PostgreSQL de la tâche est terminée. Son verrou BGG tombe aussitôt et son prochain
 *    accès à la base échoue, ce qui l'arrête ; le lot en cours est perdu, les lots précédents restent acquis.
 */
class JobRunControl
{
    public function __construct(private readonly Connection $connection) {}

    /** Pose la demande d'arrêt d'une exécution en cours. Rend false si elle n'existe pas ou n'est plus en cours. */
    public function requestStop(int $runId): bool
    {
        return $this->connection->executeStatement(
            "UPDATE job_run SET stop_requested_at = COALESCE(stop_requested_at, NOW()) WHERE id = ? AND status = 'running'",
            [$runId],
        ) > 0;
    }

    /**
     * Arrête de force une exécution en cours et la clôt dans le journal.
     *
     * @return int|null nombre de sessions terminées (0 : le processus avait déjà disparu, la ligne est seulement clôturée) ;
     *                  null si l'exécution n'existe pas ou n'est plus en cours
     */
    public function forceStop(int $runId): ?int
    {
        $running = $this->connection->fetchOne("SELECT 1 FROM job_run WHERE id = ? AND status = 'running'", [$runId]);
        if (false === $running) {
            return null;
        }

        $terminated = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM (SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE application_name = ? AND pid <> pg_backend_pid()) t',
            [JobRunRecorder::sessionName($runId)],
        );

        $this->connection->executeStatement(
            "UPDATE job_run SET status = 'cancelled', finished_at = NOW(), error = ?
             WHERE id = ? AND status = 'running'",
            [$terminated > 0 ? 'Arrêtée de force par un administrateur.' : 'Clôturée par un administrateur : aucun processus ne tournait plus.', $runId],
        );

        return $terminated;
    }
}
