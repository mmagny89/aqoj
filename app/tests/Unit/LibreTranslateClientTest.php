<?php

namespace App\Tests\Unit;

use App\Translation\LibreTranslateClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class LibreTranslateClientTest extends TestCase
{
    private function client(MockHttpClient $http, string $url = 'http://libretranslate:5000/'): LibreTranslateClient
    {
        return new LibreTranslateClient($http, $url, new NullLogger());
    }

    public function testEnvoieLesParagraphesEnAnglaisVersLeFrancaisEtRendLaTraductionDansLOrdre(): void
    {
        $sent = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $sent = ['method' => $method, 'url' => $url, 'body' => json_decode($options['body'], true)];

            return new MockResponse(json_encode(['translatedText' => ['Bonjour', 'Monde']]));
        });

        $result = $this->client($http)->translate(['Hello', 'World']);

        self::assertSame(['Bonjour', 'Monde'], $result);
        self::assertSame('POST', $sent['method']);
        self::assertSame('http://libretranslate:5000/translate', $sent['url']);
        self::assertSame(['q' => ['Hello', 'World'], 'source' => 'en', 'target' => 'fr', 'format' => 'text'], $sent['body']);
    }

    public function testUnServiceEnPanneOuUneReponseDeTraversRendNullSansLever(): void
    {
        foreach ([
            new MockResponse('', ['http_code' => 503]),
            new MockResponse('pas du json'),
            new MockResponse(json_encode(['translatedText' => ['un seul']])),   // deux paragraphes demandés
            new MockResponse(json_encode(['translatedText' => 'texte seul'])),
            new MockResponse(json_encode(['translatedText' => ['a', 3]])),
            new MockResponse(json_encode(['error' => 'limite'])),
        ] as $response) {
            self::assertNull($this->client(new MockHttpClient($response))->translate(['Hello', 'World']));
        }
    }

    public function testSansAdresseLaTraductionEstDesactiveeSansAucuneRequete(): void
    {
        $http = new MockHttpClient();

        self::assertNull($this->client($http, '')->translate(['Hello']));
        self::assertSame(0, $http->getRequestsCount());
    }

    public function testRienAEnvoyerNAppellePasLeService(): void
    {
        $http = new MockHttpClient();

        self::assertSame([], $this->client($http)->translate([]));
        self::assertSame(0, $http->getRequestsCount());
    }
}
