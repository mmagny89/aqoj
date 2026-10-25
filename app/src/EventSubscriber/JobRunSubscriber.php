<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\JobRunRecorder;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Journalise les tâches d'import et de synchronisation, qu'elles soient lancées à la main
 * ou par le planificateur. Un échec d'écriture du journal ne doit jamais faire échouer la tâche.
 */
final class JobRunSubscriber implements EventSubscriberInterface
{
    /** Les tâches suivies : celles qui écrivent dans le catalogue. */
    private const TRACKED = [
        'app:import:catalogue',
        'app:bgg:import-csv',
        'app:bgg:enrich-pending',
        'app:bgg:sync-new',
        'app:bgg:relink-expansions',
    ];

    private ?string $error = null;

    public function __construct(
        private readonly JobRunRecorder $recorder,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'onCommand',
            ConsoleEvents::ERROR => 'onError',
            ConsoleEvents::TERMINATE => 'onTerminate',
        ];
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $name = $event->getCommand()?->getName();
        $this->error = null;
        if (!\in_array($name, self::TRACKED, true)) {
            return;
        }

        $arguments = trim(preg_replace('/^\s*[\'"]?'.preg_quote((string) $name, '/').'[\'"]?\s*/', '', (string) $event->getInput()) ?? '');
        $this->guard(fn () => $this->recorder->start((string) $name, $arguments));
    }

    public function onError(ConsoleErrorEvent $event): void
    {
        $this->error = $event->getError()->getMessage();
    }

    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        if (!\in_array($event->getCommand()?->getName(), self::TRACKED, true)) {
            return;
        }

        $exitCode = $event->getExitCode();
        $status = 0 === $exitCode ? 'success' : 'failed';
        $this->guard(fn () => $this->recorder->finish($status, $exitCode, $this->error));
        $this->error = null;
    }

    private function guard(\Closure $action): void
    {
        try {
            $action();
        } catch (\Throwable $exception) {
            $this->logger->warning('Journal des tâches indisponible : '.$exception->getMessage());
        }
    }
}
