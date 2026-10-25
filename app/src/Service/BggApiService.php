<?php

namespace App\Service;

use SimpleXMLElement;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client BGG (xmlapi2). Toutes les requêtes passent par request() : cadence
 * minimale entre deux appels et nouvelles tentatives espacées y sont réglées
 * une seule fois.
 *
 * En CLI (cron), on respecte les 5 s demandées par BGG et on insiste en cas
 * d'attente (202), de limitation (429) ou d'erreur serveur. Dans une requête
 * web, on n'attend presque pas et on n'insiste pas : mieux vaut échouer vite
 * que bloquer un joueur.
 */
class BggApiService
{
    private const BASE_URL = 'https://boardgamegeek.com/xmlapi2';

    /** Au-delà, l'API répond 400 « Cannot load more than 20 items ». */
    public const MAX_IDS = 20;

    private const CLI_INTERVAL = 5.0;
    private const WEB_INTERVAL = 1.0;
    private const CLI_ATTEMPTS = 5;
    private const RETRY_BASE_SECONDS = 10;

    private float $lastCallAt = 0.0;

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

    private function isCli(): bool
    {
        return PHP_SAPI === 'cli';
    }

    /**
     * GET sur l'API, corps de la réponse en cas de 200.
     *
     * Réessaie (en espaçant) les statuts 202, 429, 5xx et les erreurs réseau,
     * dans la limite de $maxAttempts. Les autres statuts lèvent aussitôt.
     *
     * @throws BggApiException code = statut HTTP, 0 si BGG est injoignable
     */
    private function request(string $path, array $query, int $timeout, int $maxAttempts, int $retryBase): string
    {
        for ($attempt = 1; ; $attempt++) {
            $this->throttle();

            try {
                $response = $this->httpClient->request('GET', self::BASE_URL . $path, [
                    'headers' => $this->headers(),
                    'query'   => $query,
                    'timeout' => $timeout,
                ]);
                $status = $response->getStatusCode();
                if ($status === 200) {
                    return $response->getContent();
                }
            } catch (TransportExceptionInterface $e) {
                $status = 0;
                if ($attempt >= $maxAttempts) {
                    throw new BggApiException('BGG injoignable : ' . $e->getMessage(), 0, $e);
                }
            }

            $retryable = $status === 0 || $status === 202 || $status === 429 || $status >= 500;
            if (!$retryable) {
                throw new BggApiException("BGG a refusé la demande (HTTP {$status}).", $status);
            }
            if ($attempt >= $maxAttempts) {
                throw new BggApiException("BGG reste indisponible après {$attempt} tentative(s) (HTTP {$status}).", $status);
            }

            sleep($retryBase * $attempt);
        }
    }

    /** Garantit l'intervalle minimal entre deux appels du même processus. */
    private function throttle(): void
    {
        $interval = $this->isCli() ? self::CLI_INTERVAL : self::WEB_INTERVAL;
        $wait = $this->lastCallAt + $interval - microtime(true);
        if ($wait > 0) {
            usleep((int) ($wait * 1_000_000));
        }
        $this->lastCallAt = microtime(true);
    }

    private function parseXml(string $body): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        try {
            // LIBXML_NONET : aucune ressource externe chargée depuis une réponse distante.
            $xml = simplexml_load_string($body, options: LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if ($xml === false) {
            throw new BggApiException('Réponse XML invalide de BGG.');
        }

        return $xml;
    }

    /**
     * Récupère la collection BGG d'un utilisateur avec les notes personnelles.
     * Un seul appel suffit : chaque item porte son sous-type (isExpansion).
     *
     * @param bool $includeExpansions  true = inclure les extensions possédées
     * @return array<array{bggId: string, userRating: float|null, isExpansion: bool}>
     */
    public function fetchCollection(string $username, bool $includeExpansions = false): array
    {
        $query = ['username' => $username, 'own' => 1, 'stats' => 1];
        if (!$includeExpansions) {
            $query['excludesubtype'] = 'boardgameexpansion';
        }

        // BGG prépare les grosses collections en différé (202) : on patiente 3 s, 5 fois, même en web.
        try {
            $body = $this->request('/collection', $query, 30, 5, 3);
        } catch (BggApiException $e) {
            if ($e->getCode() === 401) {
                throw new BggApiException("Collection BGG privée ou utilisateur introuvable. Vérifiez que votre collection est publique sur boardgamegeek.com.", 401, $e);
            }
            throw $e;
        }

        $items = [];
        foreach ($this->parseXml($body)->item as $item) {
            $ratingRaw = (string) ($item->stats->rating['value'] ?? 'N/A');

            $items[] = [
                'bggId'       => (string) $item['objectid'],
                'userRating'  => is_numeric($ratingRaw) ? (float) $ratingRaw : null,
                'isExpansion' => (string) ($item['subtype'] ?? 'boardgame') === 'boardgameexpansion',
            ];
        }

        return $items;
    }

    /**
     * Retourne les 50 BGG IDs de la Hot List (jeux tendance du moment).
     *
     * @return string[]
     */
    public function fetchHotList(): array
    {
        $body = $this->request('/hot', ['type' => 'boardgame'], 15, $this->isCli() ? 3 : 1, self::RETRY_BASE_SECONDS);

        $ids = [];
        foreach ($this->parseXml($body)->item as $item) {
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
        $xml = $this->search($query);

        $ids = [];
        foreach ($xml->item as $item) {
            if ((int) ($item->yearpublished['value'] ?? 0) >= $minYear) {
                $ids[] = (string) $item['id'];
            }
        }

        return $ids;
    }

    /**
     * Les 8 premiers IDs BGG correspondant à un terme.
     *
     * @return string[]
     * @throws BggApiException
     */
    public function searchByQuery(string $query): array
    {
        $ids = [];
        foreach ($this->search($query)->item as $item) {
            $ids[] = (string) $item['id'];
        }

        return array_slice($ids, 0, 8);
    }

    /** @throws BggApiException */
    private function search(string $query): SimpleXMLElement
    {
        return $this->parseXml(
            $this->request('/search', ['query' => $query, 'type' => 'boardgame'], 15, $this->isCli() ? 3 : 1, self::RETRY_BASE_SECONDS)
        );
    }

    /**
     * Détails de 20 jeux au plus par appel. Un jeu absent de la réponse (supprimé
     * chez BGG) ou illisible est simplement absent du résultat : à l'appelant de
     * le dater (GameRepository::markFetchAttempted) pour ne pas le redemander.
     *
     * @param string[] $bggIds
     * @return array<int, array<string, mixed>>
     * @throws BggApiException
     */
    public function fetchGamesDetails(array $bggIds): array
    {
        if (empty($bggIds)) {
            return [];
        }
        if (count($bggIds) > self::MAX_IDS) {
            throw new \InvalidArgumentException(sprintf('Un appel thing porte de 1 à %d identifiants.', self::MAX_IDS));
        }

        $body = $this->request(
            '/thing',
            ['id' => implode(',', $bggIds), 'stats' => 1, 'versions' => 1],
            30,
            $this->isCli() ? self::CLI_ATTEMPTS : 1,
            self::RETRY_BASE_SECONDS,
        );

        $games = [];
        foreach ($this->parseXml($body)->item as $item) {
            try {
                $games[] = $this->parseGameXml($item);
            } catch (\Throwable) {
                continue;
            }
        }

        return $games;
    }

    /**
     * Édition française, tirée de `thing?versions=1`. Les versions ne portent ni description ni autre texte.
     *
     * On ne garde que les éditions monolingues françaises (une édition multilingue garde le plus souvent le titre
     * anglais, et les noms alternatifs mélangent toutes les langues). Parmi elles, l'édition retenue est la plus
     * récente **du titre le plus répandu** : prendre simplement la plus récente choisirait une extension, un format
     * « mini » ou une édition collector (« Azul Mini » pour Azul, alors que deux éditions s'appellent « Azul »).
     * À égalité de titres, la plus récente l'emporte.
     *
     * Éditeur et année sont ceux de l'édition retenue ; l'image est celle de la boîte de la plus récente édition
     * de ce même titre qui en a une, et reste vide sinon : une boîte d'un autre titre ne vaut pas mieux que l'image d'origine.
     *
     * @return array{title: string, year: ?int, publisher: ?string, imageUrl: ?string}|null
     */
    private function frenchEdition(SimpleXMLElement $item): ?array
    {
        $groups = [];   // titre normalisé => éditions françaises portant ce titre

        foreach ($item->versions->item ?? [] as $version) {
            $languages = [];
            $publishers = [];
            foreach ($version->link as $link) {
                $type = (string) $link['type'];
                if ($type === 'language') {
                    $languages[] = (string) $link['value'];
                } elseif ($type === 'boardgamepublisher') {
                    $publishers[] = $this->cleanVersionText((string) $link['value']);
                }
            }
            if ($languages !== ['French']) {
                continue;
            }

            $title = $this->cleanVersionText((string) ($version->canonicalname['value'] ?? ''));
            if ($title === '') {
                continue;
            }
            $image = trim((string) $version->image);
            $year = (int) ($version->yearpublished['value'] ?? 0);

            $groups[mb_strtolower($title)][] = [
                'title'     => $title,
                'year'      => $year > 0 ? $year : null,
                'publisher' => implode(', ', array_slice(array_filter($publishers), 0, 3)) ?: null,
                'imageUrl'  => $image === '' ? null : $this->sanitizeUtf8(str_starts_with($image, 'http') ? $image : 'https:' . $image),
            ];
        }

        if ($groups === []) {
            return null;
        }

        $newest = fn(array $editions): int => max(array_map(fn(array $e) => $e['year'] ?? 0, $editions));
        uasort($groups, fn(array $a, array $b) => [count($b), $newest($b)] <=> [count($a), $newest($a)]);

        $editions = array_values(reset($groups));
        usort($editions, fn(array $a, array $b) => ($b['year'] ?? 0) <=> ($a['year'] ?? 0));
        $withImage = array_values(array_filter($editions, fn(array $e) => $e['imageUrl'] !== null));

        return [
            'title'     => $editions[0]['title'],
            'year'      => $editions[0]['year'],
            'publisher' => $editions[0]['publisher'],
            'imageUrl'  => $withImage[0]['imageUrl'] ?? null,
        ];
    }

    /**
     * Éditeur de l'édition d'origine. BGG ne désigne pas l'édition d'origine, mais il liste sur la fiche du jeu
     * l'éditeur principal en premier, puis les autres par ordre alphabétique (KOSMOS pour Catan, Repos Production
     * pour 7 Wonders) : c'est le premier choix. Sans éditeur sur la fiche, on prend celui de la première édition
     * (toutes langues) de l'année du jeu, ou à défaut de la plus ancienne.
     */
    private function originalPublisher(SimpleXMLElement $item, ?int $gameYear): ?string
    {
        foreach ($item->link as $link) {
            if ((string) $link['type'] === 'boardgamepublisher') {
                $name = $this->cleanVersionText((string) $link['value']);
                if ($name !== '') {
                    return $name;
                }
            }
        }

        $candidates = [];   // année => éditeurs de la première édition de cette année qui en nomme
        foreach ($item->versions->item ?? [] as $version) {
            $year = (int) ($version->yearpublished['value'] ?? 0);
            if ($year <= 0 || isset($candidates[$year])) {
                continue;
            }
            $publishers = [];
            foreach ($version->link as $link) {
                if ((string) $link['type'] === 'boardgamepublisher') {
                    $publishers[] = $this->cleanVersionText((string) $link['value']);
                }
            }
            $publishers = array_slice(array_filter($publishers), 0, 3);
            if ($publishers !== []) {
                $candidates[$year] = implode(', ', $publishers);
            }
        }

        if ($candidates === []) {
            return null;
        }

        return ($gameYear !== null ? ($candidates[$gameYear] ?? null) : null) ?? $candidates[min(array_keys($candidates))];
    }

    /** BGG échappe certaines apostrophes et double parfois les espaces. */
    private function cleanVersionText(string $text): string
    {
        return $this->sanitizeUtf8(trim((string) preg_replace('/\s+/u', ' ', str_replace("\\'", "'", $text))));
    }

    private function parseGameXml(SimpleXMLElement $item): array
    {
        $bggType  = (string) $item['type'];   // 'boardgame' | 'boardgameexpansion'
        $name     = '';
        $nameFr   = null;

        foreach ($item->name as $nameEl) {
            if ((string) $nameEl['type'] === 'primary') {
                $name = $this->sanitizeUtf8((string) $nameEl['value']);
            }
        }

        $editionFr = $this->frenchEdition($item);
        $nameFr = $editionFr['title'] ?? null;

        $categories      = [];
        $mechanics       = [];
        $expansionIds    = [];
        $implementsIds   = [];  // BGG IDs des jeux dont celui-ci est une réédition
        $baseGameIds     = [];  // BGG IDs des jeux de base (si cette fiche est une extension)

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
                // Extension d'un jeu de base → liste des extensions du jeu courant
                $expansionIds[] = $linkId;
            } elseif ($type === 'boardgameexpansion' && $inbound) {
                // Ce jeu EST une extension → ID(s) du(des) jeu(x) de base
                $baseGameIds[] = $linkId;
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

        $ratings     = $item->statistics?->ratings;
        $ratingRaw   = (float) ($ratings?->average['value'] ?? 0);
        $weightRaw   = (float) ($ratings?->averageweight['value'] ?? 0);
        $usersRated  = (int)   ($ratings?->usersrated['value'] ?? 0);
        $minAge      = (int)   $item->minage['value'];

        // Rang BGG global (type "boardgame")
        $bggRank = null;
        foreach ($ratings?->ranks->rank ?? [] as $rank) {
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
            'bggType'      => in_array($bggType, ['boardgame', 'boardgameexpansion'], true) ? $bggType : 'boardgame',
            'name'         => $this->sanitizeUtf8($name ?: 'Unknown'),
            'nameFr'       => $nameFr,
            'editionFr'    => $editionFr,
            'publisherOriginal' => $this->originalPublisher($item, (int) $item->yearpublished['value'] ?: null),
            'minAge'       => $minAge > 0 ? $minAge : null,
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
            'baseGameIds'     => $baseGameIds,
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
