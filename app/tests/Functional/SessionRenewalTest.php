<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Firebase\JWT\JWT;

final class SessionRenewalTest extends ApiTestCase
{
    public function testLaSessionSeRenouvelleSansMotDePasse(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'renouvel@example.test');

        $reponse = $this->call($client, 'POST', '/api/auth/refresh', token: $token);

        self::assertResponseIsSuccessful();
        self::assertSame('renouvel@example.test', $reponse['user']['email']);
        $this->call($client, 'GET', '/api/auth/me', token: $reponse['token']);
        self::assertResponseIsSuccessful();
    }

    public function testSansJetonOuAvecUnJetonExpireLeRenouvellementEstRefuse(): void
    {
        $client = static::createClient();
        $this->register($client, 'expire-session@example.test');

        $this->call($client, 'POST', '/api/auth/refresh');
        self::assertResponseStatusCodeSame(401);

        $this->call($client, 'POST', '/api/auth/refresh', token: $this->jeton('expire-session@example.test', ['iat' => time() - 40000, 'exp' => time() - 60]));
        self::assertResponseStatusCodeSame(401);
    }

    public function testUneSessionDePlusDeTrenteJoursDoitSeReconnecter(): void
    {
        $client = static::createClient();
        $this->register($client, 'vieille@example.test');
        $vieux = $this->jeton('vieille@example.test', ['auth_time' => time() - 86400 * 31]);

        $reponse = $this->call($client, 'POST', '/api/auth/refresh', token: $vieux);

        self::assertResponseStatusCodeSame(401);
        self::assertStringContainsString('reconnectez-vous', $reponse['error']);
    }

    public function testLeRenouvellementGardeLHeureDeConnexionDOrigine(): void
    {
        $client = static::createClient();
        $this->register($client, 'origine@example.test');
        $origine = time() - 86400 * 10;

        $reponse = $this->call($client, 'POST', '/api/auth/refresh', token: $this->jeton('origine@example.test', ['auth_time' => $origine]));
        $payload = json_decode(base64_decode(strtr(explode('.', $reponse['token'])[1], '-_', '+/')), true);

        self::assertSame($origine, $payload['auth_time']);
    }

    public function testUnJetonRevoqueNeSeRenouvellePas(): void
    {
        $client = static::createClient();
        $this->register($client, 'revoque@example.test');
        $ancien = $this->jeton('revoque@example.test', ['iat' => time() - 3600, 'auth_time' => time() - 3600]);
        static::getContainer()->get(Connection::class)->executeStatement("UPDATE app_user SET password_changed_at = NOW() WHERE email = 'revoque@example.test'");

        $this->call($client, 'POST', '/api/auth/refresh', token: $ancien);

        self::assertResponseStatusCodeSame(401);
    }

    /** @param array<string, int> $surcharges */
    private function jeton(string $email, array $surcharges = []): string
    {
        $id = (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT id FROM app_user WHERE email = ?', [$email]);
        $now = time();

        return JWT::encode(array_merge(['sub' => $id, 'email' => $email, 'iat' => $now, 'auth_time' => $now, 'exp' => $now + 3600], $surcharges), (string) $_SERVER['JWT_SECRET'], 'HS256');
    }
}
