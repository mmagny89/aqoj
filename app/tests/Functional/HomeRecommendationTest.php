<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;

final class HomeRecommendationTest extends ApiTestCase
{
    public function testSansHistoriqueNiGoutsOnProposeDesNouveautesAbordables(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'froid@example.test');
        $annee = (int) date('Y');
        $this->creerJeu('Zzfroid Bon', $annee - 1, 2.3, 8.0, 5000);
        $this->creerJeu('Zzfroid Ancien', $annee - 6, 2.3, 8.5, 5000);
        $this->creerJeu('Zzfroid Expert', $annee - 1, 4.2, 8.5, 5000);
        $this->creerJeu('Zzfroid Confidentiel', $annee - 1, 2.3, 9.0, 20);
        $this->creerJeu('Zzfroid Extension', $annee - 1, 2.3, 8.5, 5000, extension: true);

        $reco = $this->call($client, 'GET', '/api/games/home-reco', token: $token);

        self::assertResponseIsSuccessful();
        self::assertTrue($reco['coldStart']);
        self::assertSame([], $reco['byDecade']);
        self::assertSame(['Zzfroid Bon'], array_column(array_column($reco['recent'], 'game'), 'name'));
        self::assertStringContainsString('facile à aborder', $reco['recent'][0]['reason']);
    }

    public function testLesGoutsDeclaresDonnentUnProfil(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'gouts@example.test');
        $annee = (int) date('Y');
        $this->creerJeu('Zzgout Coop', $annee - 1, 2.0, 7.8, 3000, familles: ['cooperation']);
        $this->creerJeu('Zzgout Encheres', $annee - 1, 2.0, 7.8, 3000, familles: ['auction']);

        $reponse = $this->call($client, 'PUT', '/api/user/taste', ['families' => ['cooperation']], $token);
        self::assertResponseIsSuccessful();
        self::assertSame(['cooperation'], $reponse['declaredFamilies']);

        $reco = $this->call($client, 'GET', '/api/games/home-reco', token: $token);

        self::assertFalse($reco['coldStart']);
        self::assertSame(['cooperation'], $reco['families']);
        self::assertSame(['cooperation'], $reco['declared']);
        self::assertSame(['Zzgout Coop'], array_column(array_column($reco['recent'], 'game'), 'name'));
    }

    public function testLaFenetreDesNouveautesCouvreCinqAns(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'fenetre@example.test');
        $annee = (int) date('Y');
        $this->creerJeu('Zzfen Limite', $annee - 4, 2.0, 7.8, 3000, familles: ['cooperation']);
        $this->creerJeu('Zzfen Dehors', $annee - 5, 2.0, 7.8, 3000, familles: ['cooperation']);
        $this->call($client, 'PUT', '/api/user/taste', ['families' => ['cooperation']], $token);

        $reco = $this->call($client, 'GET', '/api/games/home-reco', token: $token);

        self::assertSame(['Zzfen Limite'], array_column(array_column($reco['recent'], 'game'), 'name'));
        $anciens = array_merge(...array_values(array_map(fn(array $liste) => array_column(array_column($liste, 'game'), 'name'), $reco['byDecade'])));
        self::assertContains('Zzfen Dehors', $anciens);
        self::assertNotContains('Zzfen Limite', $anciens);
        self::assertStringContainsString('Nouveauté ' . ($annee - 4), $reco['recent'][0]['reason']);
    }

    public function testUneNouveauteMoinsNoteeMonteAuDessusDUnClassiqueComparable(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'ordre@example.test');
        $annee = (int) date('Y');
        // Même note brute, mais le classique a des milliers de votes : sans seuil adapté, la nouveauté serait écartée.
        $this->creerJeu('Zzord Classique', $annee - 12, 2.0, 7.9, 20000, familles: ['cooperation']);
        $this->creerJeu('Zzord Nouveau', $annee, 2.0, 7.9, 250, familles: ['cooperation']);
        $this->call($client, 'PUT', '/api/user/taste', ['families' => ['cooperation']], $token);

        $reco = $this->call($client, 'GET', '/api/games/home-reco', token: $token);

        self::assertSame(['Zzord Nouveau'], array_column(array_column($reco['recent'], 'game'), 'name'));
    }

    public function testLaDecouverteFaitRemonterLesNouveautesMemeNoyeesParLesClassiques(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'decouverte@example.test');
        $annee = (int) date('Y');
        for ($i = 1; $i <= 100; $i++) {
            $this->creerJeu("Zzdec Classique $i", $annee - 15, 2.5, 8.5, 30000);
        }
        $this->creerJeu('Zzdec Nouveau', $annee - 1, 2.5, 7.6, 800);

        $reco = $this->call($client, 'GET', '/api/games/recommendation?scope=discover', token: $token);

        self::assertResponseIsSuccessful();
        $noms = array_column(array_column($reco, 'game'), 'name');
        self::assertContains('Zzdec Nouveau', $noms);
        $nouveau = array_values(array_filter($reco, fn(array $r) => $r['game']['name'] === 'Zzdec Nouveau'))[0];
        self::assertStringStartsWith('Nouveauté ' . ($annee - 1), $nouveau['reason']);
    }

    public function testLaTableDuSoirProposeJeuxFacilesQuiLuiConviennent(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'table@example.test');
        $annee = (int) date('Y');
        $this->creerJeu('Zztab Facile', $annee - 2, 1.8, 7.6, 5000);
        $this->creerJeu('Zztab Expert', $annee - 2, 3.8, 8.6, 5000);
        $this->creerJeu('Zztab Extension', $annee - 2, 1.8, 8.6, 5000, extension: true);
        $this->creerJeu('Zztab Duo', $annee - 2, 1.8, 8.0, 5000, joueursMax: 2);

        $table = $this->call($client, 'GET', '/api/games/home-tonight?players=4&maxTime=60', token: $token);

        self::assertResponseIsSuccessful();
        self::assertSame(['Zztab Facile'], array_column(array_column($table['easy'], 'game'), 'name'));
        self::assertStringContainsString('Facile à aborder', $table['easy'][0]['reason']);
    }

    public function testLesJeuxDesAutresMembresNeSortentQuAPartirDeDeuxJoueurs(): void
    {
        $client = static::createClient();
        $moi = $this->register($client, 'moi@example.test');
        $this->register($client, 'autre1@example.test');
        $this->register($client, 'autre2@example.test');
        $annee = (int) date('Y');
        $this->creerJeu('Zzpop Partage', $annee - 3, 2.0, 7.0, 500);
        $this->creerJeu('Zzpop Solitaire', $annee - 3, 2.0, 7.0, 500);
        $this->creerJeu('Zzpop Le Mien', $annee - 3, 2.0, 7.0, 500);

        // Deux autres membres ont joué « Partage » ; un seul a joué « Solitaire » ; seul moi ai joué « Le Mien ».
        $this->partie('Zzpop Partage', 'autre1@example.test');
        $this->partie('Zzpop Partage', 'autre2@example.test');
        $this->partie('Zzpop Solitaire', 'autre1@example.test');
        $this->partie('Zzpop Solitaire', 'autre1@example.test');
        $this->partie('Zzpop Le Mien', 'moi@example.test');
        $this->partie('Zzpop Le Mien', 'autre1@example.test');

        $table = $this->call($client, 'GET', '/api/games/home-tonight', token: $moi);

        self::assertSame(['Zzpop Partage'], array_column(array_column($table['popular'], 'game'), 'name'));
        self::assertSame('Joué par 2 membres cette année', $table['popular'][0]['reason']);
        self::assertArrayNotHasKey('players', $table['popular'][0]);
    }

    public function testLaTableDuSoirExigeUneSessionSansRienReveler(): void
    {
        $client = static::createClient();

        $table = $this->call($client, 'GET', '/api/games/home-tonight');

        self::assertResponseIsSuccessful();
        self::assertSame(['easy' => [], 'popular' => []], $table);
    }

    public function testLeRecalculDesPreferencesGardeLesGoutsDeclares(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'recalcul@example.test');
        $this->call($client, 'PUT', '/api/user/taste', ['families' => ['auction', 'solo']], $token);

        $this->call($client, 'POST', '/api/user/preferences/recompute', token: $token);
        $prefs = $this->call($client, 'GET', '/api/user/preferences', token: $token);

        self::assertSame(['auction', 'solo'], $prefs['declaredFamilies']);
    }

    public function testLeQuestionnaireRefuseLesValeursInvalides(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'invalide@example.test');

        foreach ([
            ['families' => ['inconnue']],
            ['families' => ['auction', 'deck_building', 'worker_placement', 'area_control', 'cooperation', 'solo', 'legacy']],
            ['families' => 'auction'],
            [],
        ] as $corps) {
            $this->call($client, 'PUT', '/api/user/taste', $corps, $token);
            self::assertResponseStatusCodeSame(400);
        }
    }

    public function testLeQuestionnaireExigeUneSession(): void
    {
        $client = static::createClient();

        $this->call($client, 'PUT', '/api/user/taste', ['families' => ['auction']]);

        self::assertResponseStatusCodeSame(401);
    }

    /** @param string[] $familles */
    private function creerJeu(string $nom, int $annee, float $complexite, float $note, int $votes, array $familles = [], bool $extension = false, int $joueursMax = 4): void
    {
        static::getContainer()->get(Connection::class)->executeStatement(
            "INSERT INTO game (bgg_id, name, min_players, max_players, playing_time, complexity, categories, mechanics,
                               year_published, rating_bgg, users_rated, is_expansion, mechanic_families, created_at)
             VALUES (?, ?, 2, ?, 45, ?, '[]', '[\"Dice Rolling\"]', ?, ?, ?, ?, ?, NOW())",
            [
                'zz' . md5($nom), $nom, $joueursMax, $complexite, $annee, $note, $votes,
                $extension ? 't' : 'f', json_encode($familles),
            ],
        );
    }

    private function partie(string $jeu, string $email): void
    {
        $conn = static::getContainer()->get(Connection::class);
        $conn->executeStatement(
            'INSERT INTO game_session (game_id, players_count, played_at, user_id)
             VALUES ((SELECT id FROM game WHERE bgg_id = ?), 3, NOW() - INTERVAL \'10 days\', (SELECT id FROM app_user WHERE email = ?))',
            ['zz' . md5($jeu), $email],
        );
    }
}
