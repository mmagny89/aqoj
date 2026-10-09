<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
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

    protected function register(KernelBrowser $client, string $email, string $password = 'mot-de-passe-solide'): string
    {
        return $this->call($client, 'POST', '/api/auth/register', ['email' => $email, 'password' => $password])['token'];
    }
}
