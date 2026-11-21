<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;

final class ContextFiltersTest extends ApiTestCase
{
    private const EMAIL = 'contexte@example.test';

    /** @return array{0: \Symfony\Bundle\FrameworkBundle\KernelBrowser, 1: string, 2: Connection} */
    private function monde(): array
    {
        $client = static::createClient();
        $token  = $this->register($client, self::EMAIL);
        $conn   = static::getContainer()->get(Connection::class);

        // nom, joueurs min/max, durée, poids, mécaniques, âge, idéal, conseillé
        $this->jeu($conn, 'Zzctx Leger', 2, 4, 30, 1.5, '["Dice Rolling"]', 8, '[4]', '[3,4]');
        $this->jeu($conn, 'Zzctx Costaud', 1, 5, 150, 3.8, '["Hand Management"]', 14, '[2]', '[1,2]');
        $this->jeu($conn, 'Zzctx Coop', 2, 6, 45, 2.4, '["Cooperative Game"]', 10, '[5]', '[4,5]');
        $this->jeu($conn, 'Zzctx Inconnu', 2, 6, 0, 0, '[]', null, null, null);

        foreach (['Zzctx Leger', 'Zzctx Costaud', 'Zzctx Coop', 'Zzctx Inconnu'] as $nom) {
            $conn->executeStatement(
                'INSERT INTO user_game (user_id, game_id) VALUES ((SELECT id FROM app_user WHERE email = ?), (SELECT id FROM game WHERE name = ?))',
                [self::EMAIL, $nom],
            );
        }
        // Costaud est joué (une partie), les autres non.
        $conn->executeStatement(
            "INSERT INTO game_session (game_id, players_count, played_at, user_id)
             VALUES ((SELECT id FROM game WHERE name = 'Zzctx Costaud'), 2, NOW(), (SELECT id FROM app_user WHERE email = ?))",
            [self::EMAIL],
        );

        return [$client, $token, $conn];
    }

    /** @return string[] */
    private function noms(array $reponse): array
    {
        $jeux = isset($reponse['games']) ? $reponse['games'] : array_map(static fn($i) => $i['game'] ?? $i, $reponse['items'] ?? $reponse);
        $noms = array_values(array_filter(array_column($jeux, 'name'), static fn($n) => str_starts_with($n, 'Zzctx')));
        sort($noms);

        return $noms;
    }

    public function testLaLudothequeFiltreParPoidsEtGardeLesJeuxSansPoids(): void
    {
        [$client, $token] = $this->monde();

        $leger = $this->call($client, 'GET', '/api/library?weight=light', token: $token);
        self::assertSame(['Zzctx Inconnu', 'Zzctx Leger'], $this->noms($leger));

        $costaud = $this->call($client, 'GET', '/api/library?weight=heavy', token: $token);
        self::assertSame(['Zzctx Costaud', 'Zzctx Inconnu'], $this->noms($costaud));
        self::assertSame(2, $costaud['total']);
    }

    public function testLaLudothequeFiltreParDureeJoueursAgeEtMode(): void
    {
        [$client, $token] = $this->monde();

        // 1 h : Costaud (150 min) sort ; durée inconnue (0) reste.
        self::assertSame(['Zzctx Coop', 'Zzctx Inconnu', 'Zzctx Leger'], $this->noms($this->call($client, 'GET', '/api/library?maxTime=60', token: $token)));
        // 6 joueurs : seuls Coop et Inconnu les supportent.
        self::assertSame(['Zzctx Coop', 'Zzctx Inconnu'], $this->noms($this->call($client, 'GET', '/api/library?players=6', token: $token)));
        // Dès 10 ans : Costaud (14 ans) sort ; âge inconnu reste.
        self::assertSame(['Zzctx Coop', 'Zzctx Inconnu', 'Zzctx Leger'], $this->noms($this->call($client, 'GET', '/api/library?age=10', token: $token)));
        // Coopératif : la fiche non enrichie (mécaniques vides) reste ; compétitif : idem.
        self::assertSame(['Zzctx Coop', 'Zzctx Inconnu'], $this->noms($this->call($client, 'GET', '/api/library?coop=1', token: $token)));
        self::assertSame(['Zzctx Costaud', 'Zzctx Inconnu', 'Zzctx Leger'], $this->noms($this->call($client, 'GET', '/api/library?coop=0', token: $token)));
    }

    public function testSupporteIdealEtConseilleSeDistinguent(): void
    {
        [$client, $token] = $this->monde();

        // À 4 : les quatre jeux le supportent ; seul Leger est idéal à 4 (Inconnu reste, sans sondage), Leger et Coop conseillés.
        self::assertSame(['Zzctx Coop', 'Zzctx Costaud', 'Zzctx Inconnu', 'Zzctx Leger'], $this->noms($this->call($client, 'GET', '/api/library?players=4', token: $token)));
        self::assertSame(['Zzctx Inconnu', 'Zzctx Leger'], $this->noms($this->call($client, 'GET', '/api/library?players=4&fit=best', token: $token)));
        self::assertSame(['Zzctx Coop', 'Zzctx Inconnu', 'Zzctx Leger'], $this->noms($this->call($client, 'GET', '/api/library?players=4&fit=recommended', token: $token)));
    }

    public function testLaLudothequeIgnoreLeFiltreJoue(): void
    {
        [$client, $token] = $this->monde();

        // `played` est l'onglet de la ludothèque : il ne se double pas d'un filtre de contexte.
        $joues = $this->call($client, 'GET', '/api/library?played=1', token: $token);
        self::assertSame(['Zzctx Costaud'], $this->noms($joues));
    }

    public function testLesRecommandationsEtLaRechercheAppliquentLesMemesFiltres(): void
    {
        [$client, $token] = $this->monde();

        $reco = $this->call($client, 'GET', '/api/games/recommendation?weight=light&coop=0&played=0', token: $token);
        self::assertSame(['Zzctx Inconnu', 'Zzctx Leger'], $this->noms($reco));

        // Recherche par filtres seuls (sans texte ni style) : le contexte suffit.
        $recherche = $this->call($client, 'GET', '/api/games/search?weight=heavy&played=1', token: $token);
        self::assertSame(['Zzctx Costaud'], $this->noms($recherche));

        // Recherche par nom : les filtres s'appliquent aux titres trouvés.
        $texte = $this->call($client, 'GET', '/api/games/search?q=Zzctx&local=1&weight=light', token: $token);
        self::assertSame(['Zzctx Inconnu', 'Zzctx Leger'], $this->noms($texte));
    }

    public function testUneValeurInconnueDesFiltresEstIgnoree(): void
    {
        [$client, $token] = $this->monde();

        $reponse = $this->call($client, 'GET', '/api/library?weight=enorme&fit=parfait&coop=', token: $token);
        self::assertCount(4, $this->noms($reponse));
    }

    private function jeu(Connection $conn, string $nom, int $min, int $max, int $duree, float $poids, string $mecaniques, ?int $age, ?string $ideal, ?string $conseille): void
    {
        $conn->executeStatement(
            "INSERT INTO game (bgg_id, name, min_players, max_players, playing_time, complexity, categories, mechanics, year_published, rating_bgg, users_rated, is_expansion, mechanic_families, min_age, best_players, recommended_players, created_at)
             VALUES (?, ?, ?, ?, ?, ?, '[]', ?, 2015, 7.5, 1000, 'f', '[]', ?, ?, ?, NOW())",
            ['zz' . md5($nom), $nom, $min, $max, $duree, $poids, $mecaniques, $age, $ideal, $conseille],
        );
    }
}
