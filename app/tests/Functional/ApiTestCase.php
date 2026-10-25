<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Les compteurs de limitation (tentatives de connexion, inscriptions) ne doivent pas déborder d'un test à l'autre.
        static::bootKernel();
        static::getContainer()->get('cache.rate_limiter')->clear();
        static::ensureKernelShutdown();
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     */
    protected function call(KernelBrowser $client, string $method, string $uri, ?array $body = null, ?string $token = null): array
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $client->request($method, $uri, server: $server, content: $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR));

        return json_decode($client->getResponse()->getContent() ?: 'null', true) ?? [];
    }

    /**
     * Crée un compte et le rend utilisable : l'inscription ne donne plus de session avant la confirmation
     * de l'adresse, donc on confirme par SQL (le parcours par e-mail a ses propres tests) puis on se connecte.
     */
    protected function register(KernelBrowser $client, string $email, string $password = 'mot-de-passe-solide'): string
    {
        $this->call($client, 'POST', '/api/auth/register', ['email' => $email, 'password' => $password]);
        static::getContainer()->get(Connection::class)->executeStatement('UPDATE app_user SET email_verified_at = NOW() WHERE email = ?', [$email]);

        return $this->call($client, 'POST', '/api/auth/login', ['email' => $email, 'password' => $password])['token'] ?? '';
    }
}
