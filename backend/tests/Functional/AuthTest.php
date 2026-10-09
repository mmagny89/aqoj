<?php

namespace App\Tests\Functional;

final class AuthTest extends ApiTestCase
{
    public function testLInscriptionRenduUnTokenQuiOuvreLaSession(): void
    {
        $client = static::createClient();

        $token = $this->register($client, 'joueur@example.test');
        $me = $this->call($client, 'GET', '/api/auth/me', token: $token);

        self::assertResponseIsSuccessful();
        self::assertSame('joueur@example.test', $me['email']);
    }

    public function testUnEmailDejaPrisEstRefuse(): void
    {
        $client = static::createClient();
        $this->register($client, 'doublon@example.test');

        $this->call($client, 'POST', '/api/auth/register', ['email' => 'doublon@example.test', 'password' => 'mot-de-passe-solide']);

        self::assertResponseStatusCodeSame(409);
    }

    public function testUnMotDePasseTropCourtEstRefuse(): void
    {
        $client = static::createClient();

        $this->call($client, 'POST', '/api/auth/register', ['email' => 'court@example.test', 'password' => 'court']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testLaConnexionExigeLeBonMotDePasse(): void
    {
        $client = static::createClient();
        $this->register($client, 'login@example.test');

        $this->call($client, 'POST', '/api/auth/login', ['email' => 'login@example.test', 'password' => 'mauvais-mot-de-passe']);
        self::assertResponseStatusCodeSame(401);

        $this->call($client, 'POST', '/api/auth/login', ['email' => 'login@example.test', 'password' => 'mot-de-passe-solide']);
        self::assertResponseIsSuccessful();
    }

    public function testSansTokenLaSessionEstRefusee(): void
    {
        $client = static::createClient();

        $this->call($client, 'GET', '/api/auth/me');

        self::assertResponseStatusCodeSame(401);
    }
}
