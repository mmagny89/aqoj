<?php

namespace App\Scheduler;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Tâches BGG de la nuit, tenues par le service `worker` du stack (et non par un crond) :
 * l'horaire vit dans le code, il se déploie avec l'application.
 *
 * Les expressions viennent de l'environnement : la préproduction et la production partagent
 * le jeton BGG et doivent donc l'appeler à des heures différentes. Les tâches se verrouillent
 * entre elles (BggJobLock) : si l'une déborde sur la suivante, celle-ci est sautée.
 */
#[AsSchedule]
final readonly class MainSchedule implements ScheduleProviderInterface
{
    // Heure de Paris, pas UTC : « 1 h du matin » doit valoir 1 h pour les joueurs, hiver comme été.
    private const TIMEZONE = 'Europe/Paris';

    public function __construct(
        #[Autowire(env: 'BGG_RESYNC_CRON')]
        private string $resyncCron,
        #[Autowire(env: 'BGG_PENDING_CRON')]
        private string $pendingCron,
        #[Autowire(env: 'BGG_SYNC_NEW_CRON')]
        private string $syncNewCron,
        #[Autowire(env: 'BGG_IMPORT_CSV_CRON')]
        private string $importCsvCron,
        private CacheInterface $cache,
        private LockFactory $lockFactory,
    ) {}

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                // Resync incrémental : 300 jeux périmés (30 jours si classés, 90 sinon).
                RecurringMessage::cron($this->resyncCron, $this->command('app:bgg:enrich-pending --mode=resync --limit=300'), self::TIMEZONE),
                // Premier enrichissement des jeux importés par le CSV, possédés d'abord.
                RecurringMessage::cron($this->pendingCron, $this->command('app:bgg:enrich-pending --mode=pending --limit=200'), self::TIMEZONE),
                // Nouveautés : Hot List, sorties de l'année, extensions citées mais absentes.
                RecurringMessage::cron($this->syncNewCron, $this->command('app:bgg:sync-new'), self::TIMEZONE),
                // Import du CSV BGG, si un nouveau ZIP a été déposé dans var/imports/bgg/.
                RecurringMessage::cron($this->importCsvCron, $this->command('app:bgg:import-csv'), self::TIMEZONE),
            )
            // Un redéploiement à l'heure d'un passage le ferait sauter : l'état retient la dernière
            // exécution et rattrape ce qui a été manqué.
            ->stateful($this->cache)
            // Après une longue coupure, un seul rattrapage par tâche.
            ->processOnlyLastMissedRun(true)
            // Deux workers sur le même horaire déclencheraient deux fois.
            ->lock($this->lockFactory->createLock('scheduler-main'));
    }

    /** Un échec remonte jusqu'au worker, qui le journalise sur le canal « messenger » (alerte par e-mail). */
    private function command(string $input): RunCommandMessage
    {
        return new RunCommandMessage($input, throwOnFailure: true, catchExceptions: false);
    }
}
