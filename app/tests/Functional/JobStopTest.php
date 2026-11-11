<?php

namespace App\Tests\Functional;

use App\Service\JobRunControl;
use App\Service\JobRunRecorder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\ConnectionLost;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class JobStopTest extends ApiTestCase
{
    private function conn(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }

    private function execution(string $status = 'running', string $command = 'app:bgg:sync-new'): int
    {
        $this->conn()->executeStatement('INSERT INTO job_run (command, arguments, started_at, status) VALUES (?, \'\', NOW(), ?)', [$command, $status]);

        return (int) $this->conn()->lastInsertId();
    }

    private function admin(KernelBrowser $client, string $email): string
    {
        $token = $this->register($client, $email);
        $this->conn()->executeStatement('UPDATE app_user SET is_admin = TRUE WHERE email = ?', [$email]);

        return $token;
    }

    public function testUnAdministrateurDemandeLArretDUneTacheEnCours(): void
    {
        $client = static::createClient();
        $token = $this->admin($client, 'stop@example.test');
        $id = $this->execution();

        $reponse = $this->call($client, 'POST', "/api/admin/jobs/$id/stop", token: $token);

        self::assertResponseStatusCodeSame(202);
        self::assertTrue($reponse['stopRequested']);
        self::assertNotNull($this->conn()->fetchOne('SELECT stop_requested_at FROM job_run WHERE id = ?', [$id]));

        $liste = $this->call($client, 'GET', '/api/admin/jobs?limit=5', token: $token);
        $ligne = array_values(array_filter($liste['runs'], fn(array $r) => $r['id'] === $id))[0];
        self::assertSame('running', $ligne['status']);
        self::assertNotNull($ligne['stopRequestedAt']);
    }

    public function testOnNeStoppePasUneExecutionTerminee(): void
    {
        $client = static::createClient();
        $token = $this->admin($client, 'fini@example.test');
        $id = $this->execution('success');

        $this->call($client, 'POST', "/api/admin/jobs/$id/stop", token: $token);
        self::assertResponseStatusCodeSame(409);
        $this->call($client, 'POST', "/api/admin/jobs/$id/kill", token: $token);
        self::assertResponseStatusCodeSame(409);
        $this->call($client, 'POST', '/api/admin/jobs/999999999/stop', token: $token);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('success', $this->conn()->fetchOne('SELECT status FROM job_run WHERE id = ?', [$id]));
    }

    public function testUnJoueurNePeutPasArreterUneTache(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'joueur-stop@example.test');
        $id = $this->execution();

        $this->call($client, 'POST', "/api/admin/jobs/$id/stop", token: $token);
        self::assertResponseStatusCodeSame(403);
        $this->call($client, 'POST', "/api/admin/jobs/$id/kill", token: $token);
        self::assertResponseStatusCodeSame(403);
        self::assertNull($this->conn()->fetchOne('SELECT stop_requested_at FROM job_run WHERE id = ?', [$id]));
    }

    public function testLaTacheVoitLaDemandeEtSeTermineEnArretee(): void
    {
        static::bootKernel();
        $recorder = new JobRunRecorder($this->conn());
        $recorder->start('app:bgg:sync-new', '');
        $id = (int) $this->conn()->fetchOne("SELECT MAX(id) FROM job_run WHERE command = 'app:bgg:sync-new'");

        // La session porte le nom de l'exécution pendant qu'elle tourne, et plus rien après.
        self::assertSame(JobRunRecorder::sessionName($id), $this->conn()->fetchOne('SHOW application_name'));
        self::assertFalse($recorder->stopRequested());

        (new JobRunControl($this->conn()))->requestStop($id);
        // Au plus une lecture par seconde : on attend de la revoir.
        usleep(1_100_000);
        self::assertTrue($recorder->stopRequested());

        $recorder->finish('success', 0);

        self::assertSame('cancelled', $this->conn()->fetchOne('SELECT status FROM job_run WHERE id = ?', [$id]));
        self::assertSame('', $this->conn()->fetchOne('SHOW application_name'));
    }

    public function testUneTacheSansDemandeNEstPasArretee(): void
    {
        static::bootKernel();
        $recorder = new JobRunRecorder($this->conn());
        $recorder->start('app:bgg:sync-new', '');
        $id = (int) $this->conn()->fetchOne("SELECT MAX(id) FROM job_run WHERE command = 'app:bgg:sync-new'");

        self::assertFalse($recorder->stopRequested());
        $recorder->finish('success', 0);

        self::assertSame('success', $this->conn()->fetchOne('SELECT status FROM job_run WHERE id = ?', [$id]));
    }

    public function testLArretForceTermineLaSessionDeLaTacheEtClotLaLigne(): void
    {
        static::bootKernel();
        $id = $this->execution();
        // Un autre « processus » : sa propre connexion, nommée comme le fait JobRunRecorder::start().
        $process = DriverManager::getConnection($this->conn()->getParams());
        $process->fetchOne("SELECT set_config('application_name', ?, false)", [JobRunRecorder::sessionName($id)]);
        self::assertSame(1, (int) $process->fetchOne('SELECT 1'));

        $terminated = (new JobRunControl($this->conn()))->forceStop($id);

        self::assertSame(1, $terminated);
        try {
            $process->fetchOne('SELECT 1');
            self::fail('La session de la tâche aurait dû être terminée.');
        } catch (ConnectionLost) {
            self::addToAssertionCount(1);
        }
        self::assertSame(['cancelled', 'Arrêtée de force par un administrateur.'], array_values($this->conn()->fetchAssociative('SELECT status, error FROM job_run WHERE id = ?', [$id])));
    }

    public function testLArretForceNeToucheQueLaSessionDeLaBonneExecution(): void
    {
        static::bootKernel();
        $cible = $this->execution();
        $autre = $this->execution();
        $process = DriverManager::getConnection($this->conn()->getParams());
        $process->fetchOne("SELECT set_config('application_name', ?, false)", [JobRunRecorder::sessionName($autre)]);

        (new JobRunControl($this->conn()))->forceStop($cible);

        self::assertSame(1, (int) $process->fetchOne('SELECT 1'));
        self::assertSame('running', $this->conn()->fetchOne('SELECT status FROM job_run WHERE id = ?', [$autre]));
        $process->close();
    }

    public function testUneLigneOrphelineSeCloture(): void
    {
        static::bootKernel();
        $id = $this->execution();

        $terminated = (new JobRunControl($this->conn()))->forceStop($id);

        self::assertSame(0, $terminated);
        self::assertSame(['cancelled', 'Clôturée par un administrateur : aucun processus ne tournait plus.'], array_values($this->conn()->fetchAssociative('SELECT status, error FROM job_run WHERE id = ?', [$id])));
    }

    public function testUneTacheForceeNeRepasseJamaisEnSuccesSiElleTermineApres(): void
    {
        static::bootKernel();
        $recorder = new JobRunRecorder($this->conn());
        $recorder->start('app:bgg:sync-new', '');
        $id = (int) $this->conn()->fetchOne("SELECT MAX(id) FROM job_run WHERE command = 'app:bgg:sync-new'");
        (new JobRunControl($this->conn()))->forceStop($id);

        $recorder->finish('success', 0);

        self::assertSame('cancelled', $this->conn()->fetchOne('SELECT status FROM job_run WHERE id = ?', [$id]));
    }
}
