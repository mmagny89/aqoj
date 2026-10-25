<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TranslateDescriptionsCommandTest extends KernelTestCase
{
    /** @var list<string> premier paragraphe de chaque requête, dans l'ordre */
    private array $sent = [];

    private function tester(?callable $respond = null): CommandTester
    {
        static::bootKernel();
        $this->sent = [];
        $respond ??= fn(array $q) => new MockResponse(json_encode(['translatedText' => array_map(fn($p) => "FR: $p", $q)]));
        static::getContainer()->set('test.translate_http_client', new MockHttpClient(function (string $m, string $u, array $o) use ($respond): MockResponse {
            $q = json_decode($o['body'], true)['q'];
            $this->sent[] = $q[0];

            return $respond($q);
        }));

        return new CommandTester((new Application(static::$kernel))->find('app:translate:descriptions'));
    }

    public function testLesJeuxDeLAssociationPassentPuisLesJeuxPossedesPuisLesMieuxClasses(): void
    {
        $conn = static::getContainer()->get(Connection::class);
        $classe = $this->jeu('Zzcmd Classe', rang: 3);
        $simple = $this->jeu('Zzcmd Simple', rang: null);
        $possede = $this->jeu('Zzcmd Possede', rang: 90000);
        $asso = $this->jeu('Zzcmd Asso', rang: 80000);
        $conn->executeStatement("INSERT INTO app_user (email, password_hash, created_at) VALUES ('cmd@example.test', 'x', NOW())");
        $conn->executeStatement('INSERT INTO user_game (user_id, game_id) VALUES ((SELECT id FROM app_user WHERE email = ?), ?)', ['cmd@example.test', $possede]);
        $conn->executeStatement('INSERT INTO association_game (game_id) VALUES (?)', [$asso]);

        $tester = $this->tester();
        $code = $tester->execute(['--limit' => '10']);

        self::assertSame(0, $code);
        self::assertSame([
            'Description of Zzcmd Asso', 'Description of Zzcmd Possede', 'Description of Zzcmd Classe', 'Description of Zzcmd Simple',
        ], array_values(array_filter($this->sent, fn($s) => str_contains($s, 'Zzcmd'))));
        self::assertSame('FR: Description of Zzcmd Asso', $conn->fetchOne('SELECT description_fr FROM game WHERE id = ?', [$asso]));
        self::assertNotNull($conn->fetchOne('SELECT description_fr_hash FROM game WHERE id = ?', [$classe]));
        self::assertNotNull($conn->fetchOne('SELECT description_fr_at FROM game WHERE id = ?', [$simple]));
        self::assertStringContainsString('traduit(s)', $tester->getDisplay());
    }

    public function testLaLimiteEtLesJeuxDejaTraitesSontRespectes(): void
    {
        $conn = static::getContainer()->get(Connection::class);
        $this->jeu('Zzlim A', rang: 1);
        $this->jeu('Zzlim B', rang: 2);
        $this->jeu('Zzlim C', rang: 3);
        $fait = $this->jeu('Zzlim Fait', rang: 0);
        $recent = $this->jeu('Zzlim Recent', rang: 0);
        $conn->executeStatement("UPDATE game SET description_fr = 'déjà', description_fr_hash = md5(description) WHERE id = ?", [$fait]);
        $conn->executeStatement('UPDATE game SET description_fr_attempt_at = NOW() WHERE id = ?', [$recent]);

        $this->tester()->execute(['--limit' => '2']);

        self::assertSame(['Description of Zzlim A', 'Description of Zzlim B'], array_values(array_filter($this->sent, fn($s) => str_contains($s, 'Zzlim'))));
    }

    public function testUnServiceEnPanneInterromptLePassageApresTroisEchecs(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->jeu("Zzpan $i", rang: $i);
        }

        $tester = $this->tester(fn() => new MockResponse('', ['http_code' => 503]));
        $code = $tester->execute(['--limit' => '6']);

        self::assertSame(1, $code);
        self::assertCount(3, $this->sent);
        self::assertStringContainsString('ne répond pas', $tester->getDisplay());
    }

    public function testUneDemandeDArretInterrompLePassageEtLeJournalLEnregistreCommeArretee(): void
    {
        $conn = static::getContainer()->get(Connection::class);
        $this->jeu('Zzstop A', rang: 1);
        $this->jeu('Zzstop B', rang: 2);
        $this->jeu('Zzstop C', rang: 3);
        static::bootKernel();
        $translated = 0;
        static::getContainer()->set('test.translate_http_client', new MockHttpClient(function (string $m, string $u, array $o) use ($conn, &$translated): MockResponse {
            // Un administrateur demande l'arrêt pendant le premier jeu ; la tâche ne relit la demande qu'une fois par seconde.
            if (++$translated === 1) {
                $conn->executeStatement("UPDATE job_run SET stop_requested_at = NOW() WHERE command = 'app:translate:descriptions' AND status = 'running'");
                usleep(1_100_000);
            }

            return new MockResponse(json_encode(['translatedText' => array_map(fn($p) => "FR: $p", json_decode($o['body'], true)['q'])]));
        }));

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);
        $code = $application->run(new ArrayInput(['command' => 'app:translate:descriptions', '--limit' => '5']), new BufferedOutput());

        self::assertSame(0, $code);
        self::assertSame(1, $translated, 'Seul le jeu en cours est traduit, les suivants attendent le prochain passage.');
        $job = $conn->fetchAssociative("SELECT status, metrics FROM job_run WHERE command = 'app:translate:descriptions' ORDER BY id DESC LIMIT 1");
        self::assertSame('cancelled', $job['status']);
        self::assertTrue(json_decode($job['metrics'], true)['arret_demande']);
    }

    public function testLeModeDryRunNAppellePasLeService(): void
    {
        $this->jeu('Zzdry A', rang: 1);

        $tester = $this->tester();
        $tester->execute(['--dry-run' => true]);

        self::assertSame([], $this->sent);
        self::assertStringContainsString('à traduire', $tester->getDisplay());
    }

    private function jeu(string $nom, ?int $rang): int
    {
        $conn = static::getContainer()->get(Connection::class);
        $conn->executeStatement(
            "INSERT INTO game (bgg_id, name, description, min_players, max_players, playing_time, complexity, categories, mechanics,
                               year_published, bgg_rank, mechanic_families, source, created_at)
             VALUES (?, ?, ?, 2, 4, 30, 2, '[]', '[\"Dice Rolling\"]', 2020, ?, '[]', 'bgg_api', NOW())",
            ['zzcm' . md5($nom), $nom, "Description of $nom", $rang],
        );

        return (int) $conn->lastInsertId();
    }
}
