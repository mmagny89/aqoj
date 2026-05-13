<?php

namespace App\Service;

use SimpleXMLElement;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BggApiService
{
    private const BASE_URL = 'https://boardgamegeek.com/xmlapi2';

    private const HEADERS = [
        'User-Agent' => 'AQuoiOnJoue/1.0 (board game recommendation app)',
        'Accept' => 'application/xml',
    ];

    public function __construct(private readonly HttpClientInterface $httpClient) {}

    public function fetchCollectionIds(string $username): array
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $response = $this->httpClient->request('GET', self::BASE_URL . '/collection', [
                'headers' => self::HEADERS,
                'query' => [
                    'username' => $username,
                    'own' => 1,
                    'excludesubtype' => 'boardgameexpansion',
                ],
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 202) {
                sleep(3);
                continue;
            }

            if ($statusCode === 401) {
                throw new \RuntimeException("Collection BGG privée ou utilisateur introuvable. Vérifiez que votre collection est publique sur boardgamegeek.com.");
            }

            if ($statusCode === 200) {
                $xml = simplexml_load_string($response->getContent());

                if ($xml === false) {
                    throw new \RuntimeException('Invalid XML response from BGG');
                }

                $ids = [];
                foreach ($xml->item as $item) {
                    $ids[] = (string) $item['objectid'];
                }

                return $ids;
            }

            throw new \RuntimeException("BGG API returned status {$statusCode}");
        }

        throw new \RuntimeException('BGG API timeout: collection not ready after retries');
    }

    public function fetchGamesDetails(array $bggIds): array
    {
        if (empty($bggIds)) {
            return [];
        }

        $response = $this->httpClient->request('GET', self::BASE_URL . '/thing', [
            'headers' => self::HEADERS,
            'query' => [
                'id' => implode(',', $bggIds),
                'stats' => 1,
            ],
            'timeout' => 30,
        ]);

        $xml = simplexml_load_string($response->getContent());

        if ($xml === false) {
            throw new \RuntimeException('Invalid XML response from BGG');
        }

        $games = [];
        foreach ($xml->item as $item) {
            try {
                $games[] = $this->parseGameXml($item);
            } catch (\Throwable) {
                continue;
            }
        }

        return $games;
    }

    public function searchByQuery(string $query): array
    {
        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . '/search', [
                'headers' => self::HEADERS,
                'query' => ['query' => $query, 'type' => 'boardgame'],
                'timeout' => 15,
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $xml = simplexml_load_string($response->getContent());
            if ($xml === false) {
                return [];
            }

            $ids = [];
            foreach ($xml->item as $item) {
                $ids[] = (string) $item['id'];
            }

            return array_slice($ids, 0, 8);
        } catch (\Throwable) {
            return [];
        }
    }

    private function parseGameXml(SimpleXMLElement $item): array
    {
        $name = '';
        foreach ($item->name as $nameEl) {
            if ((string) $nameEl['type'] === 'primary') {
                $name = (string) $nameEl['value'];
                break;
            }
        }

        $categories = [];
        $mechanics = [];
        foreach ($item->link as $link) {
            $type = (string) $link['type'];
            $value = (string) $link['value'];
            if ($type === 'boardgamecategory') {
                $categories[] = $value;
            } elseif ($type === 'boardgamemechanic') {
                $mechanics[] = $value;
            }
        }

        $description = (string) $item->description;
        $description = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $description = strip_tags($description);
        $description = trim($description);
        if (strlen($description) > 2000) {
            $description = substr($description, 0, 2000) . '...';
        }

        $imageUrl = trim((string) $item->image);
        if ($imageUrl && !str_starts_with($imageUrl, 'http')) {
            $imageUrl = 'https:' . $imageUrl;
        }

        $ratingRaw = (float) $item->statistics->ratings->average['value'];
        $weightRaw = (float) $item->statistics->ratings->averageweight['value'];

        return [
            'bggId' => (string) $item['id'],
            'name' => $name ?: 'Unknown',
            'description' => $description ?: null,
            'minPlayers' => max(1, (int) $item->minplayers['value']),
            'maxPlayers' => max(1, (int) $item->maxplayers['value']),
            'playingTime' => max(0, (int) $item->playingtime['value']),
            'complexity' => $weightRaw > 0 ? $weightRaw : 0.0,
            'categories' => $categories,
            'mechanics' => $mechanics,
            'imageUrl' => $imageUrl ?: null,
            'yearPublished' => (int) $item->yearpublished['value'] ?: null,
            'ratingBgg' => $ratingRaw > 0 ? $ratingRaw : null,
        ];
    }
}
