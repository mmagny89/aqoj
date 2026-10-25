<?php

namespace App\Translation;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client de LibreTranslate, auto-hébergé sur le réseau du projet (service `libretranslate`) : aucun texte
 * ne quitte l'hôte. Une panne n'est jamais une erreur pour l'appelant : il retombe sur le texte d'origine.
 */
class LibreTranslateClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'LIBRETRANSLATE_URL')]
        private readonly string $baseUrl,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Traduit de l'anglais vers le français, un paragraphe par élément.
     *
     * @param list<string> $paragraphs
     *
     * @return list<string>|null null si le service est injoignable ou répond de travers
     */
    public function translate(array $paragraphs, float $timeout = 30.0): ?array
    {
        if ($paragraphs === []) {
            return [];
        }
        if ($this->baseUrl === '') {
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/translate', [
                'json'    => ['q' => $paragraphs, 'source' => 'en', 'target' => 'fr', 'format' => 'text'],
                'timeout' => $timeout,
            ]);
            $translated = $response->toArray()['translatedText'] ?? null;
        } catch (ExceptionInterface|\JsonException $e) {
            $this->logger->warning('Traduction indisponible : {message}', ['message' => $e->getMessage()]);

            return null;
        }

        if (!is_array($translated) || !array_is_list($translated) || count($translated) !== count($paragraphs)) {
            $this->logger->warning('Réponse de traduction inattendue.');

            return null;
        }
        foreach ($translated as $text) {
            if (!is_string($text)) {
                return null;
            }
        }

        return $translated;
    }
}
