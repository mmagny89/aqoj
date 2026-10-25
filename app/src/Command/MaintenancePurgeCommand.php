<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\JobRunRecorder;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:maintenance:purge',
    description: 'Purge ce qui ne sert plus : journal des tâches ancien, jetons de compte périmés',
    help: <<<'HELP'
        Planifiée chaque dimanche à 5 h par le worker. Supprime :
          - les lignes du journal des tâches (job_run) plus vieilles que --jobs-days (90 par défaut) ;
          - les jetons de mot de passe oublié déjà utilisés ou expirés depuis plus de 7 jours.
        --dry-run compte sans rien supprimer.
        HELP,
)]
final readonly class MaintenancePurgeCommand
{
    public function __construct(
        private Connection $connection,
        private JobRunRecorder $recorder,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(name: 'jobs-days', description: 'Âge au-delà duquel une ligne du journal est supprimée')]
        int $jobsDays = 90,
        #[Option(name: 'dry-run', description: 'Compter sans supprimer')]
        bool $dryRun = false,
    ): int {
        if ($jobsDays < 7) {
            $io->error('Le journal se conserve au moins 7 jours.');

            return Command::FAILURE;
        }

        $report = [];
        // Une exécution encore « en cours » n'est jamais purgée : seules celles terminées le sont.
        $report['journal'] = $this->purge(
            $dryRun,
            'job_run',
            "finished_at IS NOT NULL AND finished_at < NOW() - make_interval(days => $jobsDays)",
        );
        $report['jetons'] = $this->purge(
            $dryRun,
            'password_reset_request',
            "(used_at IS NOT NULL AND used_at < NOW() - INTERVAL '7 days') OR expires_at < NOW() - INTERVAL '7 days'",
        );

        $this->recorder->note(['dry_run' => $dryRun] + array_map(static fn (int $n): int => $n, $report));
        $io->success(\sprintf(
            '%s : %d ligne(s) du journal, %d jeton(s).',
            $dryRun ? 'À supprimer' : 'Supprimé',
            $report['journal'],
            $report['jetons'],
        ));

        return Command::SUCCESS;
    }

    private function purge(bool $dryRun, string $table, string $where): int
    {
        // Table et condition sont des constantes de cette classe, jamais une saisie.
        return (int) ($dryRun
            ? $this->connection->fetchOne("SELECT COUNT(*) FROM $table WHERE $where")
            : $this->connection->executeStatement("DELETE FROM $table WHERE $where"));
    }
}
