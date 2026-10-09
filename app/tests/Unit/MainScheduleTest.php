<?php

namespace App\Tests\Unit;

use App\Scheduler\MainSchedule;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Generator\MessageContext;

final class MainScheduleTest extends TestCase
{
    public function testLesQuatreTachesBggSontPlanifieesDansLOrdre(): void
    {
        $schedule = new MainSchedule('0 1 * * *', '0 2 * * *', '0 3 * * *', '0 4 * * *', new ArrayAdapter(), new LockFactory(new InMemoryStore()));

        $commands = [];
        $provided = $schedule->getSchedule();
        foreach ($provided->getRecurringMessages() as $recurring) {
            $now = new \DateTimeImmutable('2026-10-10 00:00');
            $messages = $recurring->getProvider()->getMessages(new MessageContext('default', 'test', $recurring->getTrigger(), $now));
            foreach ($messages as $message) {
                self::assertInstanceOf(RunCommandMessage::class, $message);
                self::assertTrue($message->throwOnFailure);
                $commands[] = $message->input;
            }
        }

        self::assertSame([
            'app:bgg:enrich-pending --mode=resync --limit=300',
            'app:bgg:enrich-pending --mode=pending --limit=200',
            'app:bgg:sync-new',
            'app:bgg:import-csv',
        ], $commands);
    }
}
