<?php

namespace App\Tests\Functional;

final class AccountTest extends ApiTestCase
{
    public function testLExportContientLesDonneesDuJoueur(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'export@example.test');

        $export = $this->call($client, 'GET', '/api/user/export', token: $token);

        self::assertResponseIsSuccessful();
        self::assertSame('export@example.test', $export['account']['email']);
        self::assertSame([], $export['collection']);
        self::assertSame([], $export['sessions']);
        self::assertStringContainsString('attachment', (string) $client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testLExportExigeUneAuthentification(): void
    {
        $client = static::createClient();

        $this->call($client, 'GET', '/api/user/export');

        self::assertResponseStatusCodeSame(401);
    }

    public function testLaSuppressionExigeLeMotDePasse(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'garde@example.test');

        $this->call($client, 'DELETE', '/api/user/account', ['password' => 'mauvais'], $token);
        self::assertResponseStatusCodeSame(403);

        $this->call($client, 'GET', '/api/auth/me', token: $token);
        self::assertResponseIsSuccessful();
    }

    public function testLaSuppressionEffaceLeCompte(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'partant@example.test');

        $this->call($client, 'DELETE', '/api/user/account', ['password' => 'mot-de-passe-solide'], $token);
        self::assertResponseIsSuccessful();

        // Le jeton reste valide, mais son compte n'existe plus.
        $this->call($client, 'GET', '/api/auth/me', token: $token);
        self::assertResponseStatusCodeSame(401);

        $this->call($client, 'POST', '/api/auth/login', ['email' => 'partant@example.test', 'password' => 'mot-de-passe-solide']);
        self::assertResponseStatusCodeSame(401);
    }
}
