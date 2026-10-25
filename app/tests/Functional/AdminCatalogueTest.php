<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Service\JobRunRecorder;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class AdminCatalogueTest extends ApiTestCase
{
    public function testLeJournalMontreLesExecutionsEtLaDerniereDeChaqueTache(): void
    {
        $client = static::createClient();
        $token = $this->adminToken($client);
        $recorder = static::getContainer()->get(JobRunRecorder::class);

        $recorder->start('app:bgg:sync-new', '');
        $recorder->note(['nouveaux' => 3]);
        $recorder->finish('success', 0);
        $recorder->start('app:bgg:sync-new', '');
        $recorder->note(['skipped' => 'verrou BGG pris']);
        $recorder->finish('success', 0);
        $recorder->start('app:bgg:enrich-pending', '--mode=pending');
        $recorder->finish('failed', 1, 'BGG indisponible');

        $body = $this->call($client, 'GET', '/api/admin/jobs', token: $token);

        self::assertResponseIsSuccessful();
        self::assertCount(3, $body['runs']);
        self::assertSame('failed', $body['runs'][0]['status']);
        self::assertSame('BGG indisponible', $body['runs'][0]['error']);
        $derniers = array_column($body['last'], null, 'command');
        self::assertSame('skipped', $derniers['app:bgg:sync-new']['status']);
        self::assertSame(['skipped' => 'verrou BGG pris'], $derniers['app:bgg:sync-new']['metrics']);

        $filtre = $this->call($client, 'GET', '/api/admin/jobs?status=failed', token: $token);
        self::assertCount(1, $filtre['runs']);
    }

    public function testUneExecutionOubliéeEnCoursSeVoitInterrompue(): void
    {
        $client = static::createClient();
        $token = $this->adminToken($client);
        static::getContainer()->get(JobRunRecorder::class)->start('app:bgg:enrich-pending', '');
        static::getContainer()->get(Connection::class)->executeStatement("UPDATE job_run SET started_at = NOW() - INTERVAL '13 hours'");

        $body = $this->call($client, 'GET', '/api/admin/jobs', token: $token);

        self::assertSame('interrupted', $body['runs'][0]['status']);
    }

    public function testLExplorateurFiltreLesJeuxSelonLeurEtat(): void
    {
        $client = static::createClient();
        $token = $this->adminToken($client);
        $connection = static::getContainer()->get(Connection::class);
        foreach ([['990201', 'bgg_csv', null], ['990202', 'bgg_csv', 'NOW()'], ['990203', 'bgg_api', null]] as [$bggId, $source, $attempt]) {
            $connection->executeStatement(
                "INSERT INTO game (bgg_id, name, source, created_at, last_fetch_attempt_at, categories, mechanics) VALUES (?, ?, ?, NOW(), ".($attempt ?? 'NULL').", '[]', '[]')",
                [$bggId, 'Zzadmin '.$bggId, $source],
            );
        }

        $jamais = $this->call($client, 'GET', '/api/admin/games?filter=never_attempted&q=Zzadmin', token: $token);
        self::assertSame(['990201'], array_column($jamais['items'], 'bggId'));

        $sansReponse = $this->call($client, 'GET', '/api/admin/games?filter=unanswered&q=Zzadmin', token: $token);
        self::assertSame(['990202'], array_column($sansReponse['items'], 'bggId'));

        $enrichis = $this->call($client, 'GET', '/api/admin/games?filter=enriched&q=990203', token: $token);
        self::assertSame(1, $enrichis['total']);

        $compteurs = $this->call($client, 'GET', '/api/admin/games/filters', token: $token);
        self::assertGreaterThanOrEqual(2, $compteurs['pending']);

        $fiche = $this->call($client, 'GET', '/api/admin/games/990201', token: $token);
        self::assertSame('Zzadmin 990201', $fiche['name']);

        $this->call($client, 'GET', '/api/admin/games?filter=nimporte', token: $token);
        self::assertResponseStatusCodeSame(400);
        $this->call($client, 'GET', '/api/admin/games/999999999', token: $token);
        self::assertResponseStatusCodeSame(404);
    }

    private function adminToken(KernelBrowser $client): string
    {
        $token = $this->register($client, 'admin-catalogue@example.test');
        static::getContainer()->get(Connection::class)->executeStatement("UPDATE app_user SET is_admin = TRUE WHERE email = 'admin-catalogue@example.test'");

        return $token;
    }
}
