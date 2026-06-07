<?php

namespace App\Service;

use SimpleXMLElement;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BggApiService
{
    private const BASE_URL = 'https://boardgamegeek.com/xmlapi2';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $bggToken,
    ) {}

    private function headers(): array
    {
        $headers = [
            'User-Agent' => 'AQuoiOnJoue/1.0 (board game recommendation app)',
            'Accept' => 'application/xml',
        ];

        if ($this->bggToken !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->bggToken;
        }

        return $headers;
    }

    /**
     * Récupère la collection BGG d'un utilisateur avec les notes personnelles.
     *
     * @return array<array{bggId: string, userRating: float|null}>
     */
    public function fetchCollection(string $username): array
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $response = $this->httpClient->request('GET', self::BASE_URL . '/collection', [
                'headers' => $this->headers(),
                'query' => [
                    'username'       => $username,
                    'own'            => 1,
                    'excludesubtype' => 'boardgameexpansion',
                    'stats'          => 1,  // inclut les notes utilisateur
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

                $items = [];
                foreach ($xml->item as $item) {
                    $ratingRaw = (string) ($item->stats->rating['value'] ?? 'N/A');
                    $userRating = is_numeric($ratingRaw) ? (float) $ratingRaw : null;

                    $items[] = [
                        'bggId'      => (string) $item['objectid'],
                        'userRating' => $userRating,
                    ];
                }

                return $items;
            }

            throw new \RuntimeException("BGG API returned status {$statusCode}");
        }

        throw new \RuntimeException('BGG API timeout: collection not ready after retries');
    }

    /**
     * Retourne les 50 BGG IDs de la Hot List (jeux tendance du moment).
     *
     * @return string[]
     */
    public function fetchHotList(): array
    {
        $response = $this->httpClient->request('GET', self::BASE_URL . '/hot', [
            'headers' => $this->headers(),
            'query'   => ['type' => 'boardgame'],
            'timeout' => 15,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('BGG Hot List returned HTTP ' . $response->getStatusCode());
        }

        $xml = simplexml_load_string($response->getContent());
        if ($xml === false) {
            throw new \RuntimeException('Invalid XML from BGG hot list');
        }

        $ids = [];
        foreach ($xml->item as $item) {
            $ids[] = (string) $item['id'];
        }

        return $ids;
    }

    /**
     * Recherche sur BGG par terme, filtrée sur une année de publication minimale.
     * Retourne uniquement les IDs dont yearPublished >= $minYear.
     *
     * @return string[]
     */
    public function searchByYear(string $query, int $minYear): array
    {
        $response = $this->httpClient->request('GET', self::BASE_URL . '/search', [
            'headers' => $this->headers(),
            'query'   => ['query' => $query, 'type' => 'boardgame'],
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
            $pubYear = (int) ($item->yearpublished['value'] ?? 0);
            if ($pubYear >= $minYear) {
                $ids[] = (string) $item['id'];
            }
        }

        return $ids;
    }

    /** @deprecated Utiliser fetchCollection() qui retourne aussi les notes utilisateur */
    public function fetchCollectionIds(string $username): array
    {
        return array_column($this->fetchCollection($username), 'bggId');
    }

    public function fetchGamesDetails(array $bggIds): array
    {
        if (empty($bggIds)) {
            return [];
        }

        // Retry avec backoff exponentiel sur les 429 (rate-limit BGG)
        $waits = [10, 30, 60]; // secondes d'attente entre tentatives

        foreach ([null, ...$waits] as $attempt => $waitBefore) {
            if ($waitBefore !== null) {
                sleep($waitBefore);
            }

            $response = $this->httpClient->request('GET', self::BASE_URL . '/thing', [
                'headers' => $this->headers(),
                'query'   => ['id' => implode(',', $bggIds), 'stats' => 1],
                'timeout' => 30,
            ]);

            $status = $response->getStatusCode();

            if ($status === 429) {
                if ($waitBefore === 60) {
                    // Dernière tentative échouée
                    throw new \RuntimeException('BGG rate-limit (429) persistant après 3 tentatives.');
                }
                // On laisse la boucle faire la prochaine itération avec son délai
                continue;
            }

            if ($status !== 200) {
                throw new \RuntimeException("BGG API returned HTTP {$status}");
            }

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

        // Ne devrait jamais être atteint
        throw new \RuntimeException('BGG fetchGamesDetails : toutes les tentatives ont échoué.');
    }

    public function searchByQuery(string $query): array
    {
        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . '/search', [
                'headers' => $this->headers(),
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

        $categories      = [];
        $mechanics       = [];
        $expansionIds    = [];
        $implementsIds   = [];  // BGG IDs des jeux dont celui-ci est une réédition

        foreach ($item->link as $link) {
            $type    = (string) $link['type'];
            $value   = $this->sanitizeUtf8((string) $link['value']);
            $linkId  = (string) $link['id'];
            $inbound = ((string) ($link['inbound'] ?? '')) === 'true';

            if ($type === 'boardgamecategory') {
                $categories[] = $value;
            } elseif ($type === 'boardgamemechanic') {
                $mechanics[] = $value;
            } elseif ($type === 'boardgameexpansion' && !$inbound) {
                $expansionIds[] = $linkId;
            } elseif ($type === 'boardgameimplementation' && $inbound) {
                $implementsIds[] = $linkId;
            }
        }

        $description = (string) $item->description;
        $description = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $description = strip_tags($description);
        $description = $this->sanitizeUtf8($description);
        $description = trim($description);
        // mb_substr évite de couper un caractère multi-octet en plein milieu
        if (mb_strlen($description) > 2000) {
            $description = mb_substr($description, 0, 2000) . '...';
        }

        $imageUrl = trim((string) $item->image);
        if ($imageUrl && !str_starts_with($imageUrl, 'http')) {
            $imageUrl = 'https:' . $imageUrl;
        }

        $ratingRaw   = (float) $item->statistics->ratings->average['value'];
        $weightRaw   = (float) $item->statistics->ratings->averageweight['value'];
        $usersRated  = (int)   $item->statistics->ratings->usersrated['value'];

        // Rang BGG global (type "boardgame")
        $bggRank = null;
        foreach ($item->statistics->ratings->ranks->rank ?? [] as $rank) {
            if ((string) $rank['name'] === 'boardgame') {
                $rankVal = (string) $rank['value'];
                if (is_numeric($rankVal)) {
                    $bggRank = (int) $rankVal;
                }
                break;
            }
        }

        return [
            'bggId'        => (string) $item['id'],
            'name'         => $this->sanitizeUtf8($name ?: 'Unknown'),
            'description'  => $description ?: null,
            'minPlayers'   => max(1, (int) $item->minplayers['value']),
            'maxPlayers'   => max(1, (int) $item->maxplayers['value']),
            'playingTime'  => max(0, (int) $item->playingtime['value']),
            'complexity'   => $weightRaw > 0 ? $weightRaw : 0.0,
            'categories'   => $categories,
            'mechanics'    => $mechanics,
            'imageUrl'     => $imageUrl ?: null,
            'yearPublished'=> (int) $item->yearpublished['value'] ?: null,
            'ratingBgg'       => $ratingRaw > 0 ? $ratingRaw : null,
            'bggRank'         => $bggRank,
            'usersRated'      => $usersRated > 0 ? $usersRated : null,
            'expansionIds'    => $expansionIds,
            'implementsIds'   => $implementsIds,
        ];
    }

    /**
     * Supprime les octets invalides en UTF-8 que PostgreSQL refuse.
     *
     * iconv avec //IGNORE est la méthode la plus stricte : il valide octet par octet
     * et abandonne silencieusement toute séquence non conforme (ex: 0xe3 0x2e 0x2e,
     * caractères japonais mal encodés, etc.).
     */
    private function sanitizeUtf8(string $str): string
    {
        // Passe 1 : iconv//IGNORE — drop hard des octets illégaux
        $clean = iconv('UTF-8', 'UTF-8//IGNORE', $str);
        if ($clean === false) {
            $clean = '';
        }

        // Passe 2 : supprime les caractères de contrôle parasites (sauf \t \n \r)
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $clean) ?? $clean;

        return $clean;
    }
}
