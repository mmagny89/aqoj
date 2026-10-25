<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;

final class HomeRecommendationTest extends ApiTestCase
{
    public function testLesNouveautesSontLeTopBggDesSortiesRecentesEtAVenir(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'froid@example.test');
        $annee = (int) date('Y');
        $this->creerJeu('Zznouv Suivi', $annee, 2.3, 8.0, 5000);
        $this->creerJeu('Zznouv AVenir', $annee + 1, 3.0, 7.0, 800);
        $this->creerJeu('Zznouv Discret', $annee, 2.3, 7.5, 60);
        $this->creerJeu('Zznouv Ancien', $annee - 3, 2.3, 8.5, 9000);
        $this->creerJeu('Zznouv Trop Loin', $annee + 3, 2.3, 8.5, 9000);
        $this->creerJeu('Zznouv Mediocre', $annee, 2.3, 4.5, 4000);
        $this->creerJeu('Zznouv Confidentiel', $annee, 2.3, 9.0, 5);
        $this->creerJeu('Zznouv Extension', $annee, 2.3, 8.5, 6000, extension: true);

        $reco = $this->call($client, 'GET', '/api/games/home-reco', token: $token);

        self::assertResponseIsSuccessful();
        self::assertTrue($reco['coldStart']);
        // Les plus votés d'abord ; ni extension, ni jeu à moins de 6/10, ni jeu quasi inconnu, ni hors fenêtre.
        self::assertSame(['Zznouv Suivi', 'Zznouv AVenir', 'Zznouv Discret'], array_column(array_column($reco['recent'], 'game'), 'name'));
        self::assertStringContainsString('Sorti en ' . $annee, $reco['recent'][0]['reason']);
        self::assertStringContainsString('À paraître en ' . ($annee + 1), $reco['recent'][1]['reason']);
    }

    public function testLesNouveautesNeProposentPasCeQueLeJoueurAdejaEtSontLimiteesADix(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'dix@example.test');
        $annee = (int) date('Y');
        for ($i = 1; $i <= 12; $i++) {
            $this->creerJeu(sprintf('Zzdix %02d', $i), $annee, 2.0, 7.0, 1000 - $i);
        }
        static::getContainer()->get(Connection::class)->executeStatement(
            'INSERT INTO user_game (user_id, game_id) VALUES ((SELECT id FROM app_user WHERE email = ?), (SELECT id FROM game WHERE name = ?))',
            ['dix@example.test', 'Zzdix 01'],
        );

        $reco = $this->call($client, 'GET', '/api/games/home-reco', token: $token);
        $noms = array_column(array_column($reco['recent'], 'game'), 'name');

        self::assertCount(10, $noms);
        self::assertNotContains('Zzdix 01', $noms);
        self::assertSame('Zzdix 02', $noms[0]);
    }

    public function testLesGoutsDeclaresDonnentUnProfil(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'gouts@example.test');
        $annee = (int) date('Y');
        $this->creerJeu('Zzgout Coop', $annee, 2.0, 7.8, 3000, familles: ['cooperation']);
        $this->creerJeu('Zzgout Encheres', $annee, 2.0, 7.8, 3000, familles: ['auction']);

        $reponse = $this->call($client, 'PUT', '/api/user/taste', ['families' => ['cooperation']], $token);
        self::assertResponseIsSuccessful();
        self::assertSame(['cooperation'], $reponse['declaredFamilies']);

        $reco = $this->call($client, 'GET', '/api/games/home-reco', token: $token);

        self::assertFalse($reco['coldStart']);
        self::assertSame(['cooperation'], $reco['families']);
        self::assertSame(['cooperation'], $reco['declared']);
        // Les goûts n'orientent plus les nouveautés : ils classent les décennies et les recommandations.
        self::assertSame(['Zzgout Coop', 'Zzgout Encheres'], array_column(array_column($reco['recent'], 'game'), 'name'));
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

    public function testLePerimetreLudothequeNeGardeQueLesJeuxDeLAssociationEtDesMembres(): void
    {
        $client = static::createClient();
        $moi = $this->register($client, 'perimetre@example.test');
        $this->register($client, 'voisin@example.test');
        $annee = (int) date('Y');
        $this->creerJeu('Zzper Asso', $annee, 1.8, 7.6, 5000);
        $this->creerJeu('Zzper Voisin', $annee, 1.8, 7.6, 5000);
        $this->creerJeu('Zzper Le Mien', $annee, 1.8, 7.6, 5000);
        $this->creerJeu('Zzper Hors', $annee, 1.8, 7.6, 5000);
        $this->creerJeu('Zzper Modeste', $annee, 1.8, 5.0, 10);
        $conn = static::getContainer()->get(Connection::class);
        foreach (['Zzper Asso', 'Zzper Modeste'] as $jeu) {
            $conn->executeStatement('INSERT INTO association_game (game_id) SELECT id FROM game WHERE bgg_id = ?', ['zz' . md5($jeu)]);
        }
        foreach ([['Zzper Voisin', 'voisin@example.test'], ['Zzper Le Mien', 'perimetre@example.test']] as [$jeu, $email]) {
            $conn->executeStatement(
                'INSERT INTO user_game (user_id, game_id) VALUES ((SELECT id FROM app_user WHERE email = ?), (SELECT id FROM game WHERE bgg_id = ?))',
                [$email, 'zz' . md5($jeu)],
            );
        }

        $table = $this->call($client, 'GET', '/api/games/home-tonight?scope=library', token: $moi);
        $noms = array_column(array_column($table['easy'], 'game'), 'name');
        sort($noms);
        // Aucun seuil de note ni de votes en ludothèque : « Modeste » (5/10, 10 votes) est proposé.
        self::assertSame(['Zzper Asso', 'Zzper Le Mien', 'Zzper Modeste', 'Zzper Voisin'], $noms);

        $oublies = $this->call($client, 'GET', '/api/games/forgotten?scope=library', token: $moi);
        $noms = array_column(array_column($oublies, 'game'), 'name');
        sort($noms);
        self::assertSame(['Zzper Asso', 'Zzper Le Mien', 'Zzper Modeste', 'Zzper Voisin'], $noms);

        $liste = $this->call($client, 'GET', '/api/games/recommendation?scope=library', token: $moi);
        $noms = array_column(array_column($liste, 'game'), 'name');
        sort($noms);
        self::assertSame(['Zzper Asso', 'Zzper Le Mien', 'Zzper Modeste', 'Zzper Voisin'], $noms);

        // Les nouveautés BGG ne dépendent pas du périmètre, mais jamais ce que j'ai déjà ; « Modeste » (5/10, 10 votes) est écartée.
        $reco = $this->call($client, 'GET', '/api/games/home-reco?scope=library', token: $moi);
        $noms = array_column(array_column($reco['recent'], 'game'), 'name');
        sort($noms);
        self::assertSame(['Zzper Asso', 'Zzper Hors', 'Zzper Voisin'], $noms);
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

    public function testLesGoutsDeclaresPassentDevantLHistoriqueEtLeCompletent(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'melange@example.test');
        $annee = (int) date('Y');
        $this->creerJeu('Zzmel Possede', $annee - 8, 2.0, 8.0, 3000, familles: ['auction', 'deck_building']);
        static::getContainer()->get(Connection::class)->executeStatement(
            'INSERT INTO user_game (user_id, game_id, bgg_user_rating)
             VALUES ((SELECT id FROM app_user WHERE email = ?), (SELECT id FROM game WHERE bgg_id = ?), 8)',
            ['melange@example.test', 'zz' . md5('Zzmel Possede')],
        );

        $sans = $this->call($client, 'GET', '/api/games/home-reco', token: $token);
        self::assertEqualsCanonicalizing(['auction', 'deck_building'], $sans['families']);

        $this->call($client, 'PUT', '/api/user/taste', ['families' => ['cooperation']], $token);
        $avec = $this->call($client, 'GET', '/api/games/home-reco', token: $token);

        self::assertSame('cooperation', $avec['families'][0]);
        self::assertEqualsCanonicalizing(['cooperation', 'auction', 'deck_building'], $avec['families']);
    }

    public function testLaTableChoisieEstEnregistreeEtRenduePourLAccueil(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'tablesave@example.test');

        self::assertNull($this->call($client, 'GET', '/api/games/home-reco', token: $token)['table']);

        $reponse = $this->call($client, 'PUT', '/api/user/table', ['players' => 6, 'maxTime' => 90], $token);
        self::assertResponseIsSuccessful();
        self::assertSame(['players' => 6, 'maxTime' => 90], $reponse['table']);

        self::assertSame(['players' => 6, 'maxTime' => 90], $this->call($client, 'GET', '/api/games/home-reco', token: $token)['table']);
        self::assertSame(['players' => 6, 'maxTime' => 90], $this->call($client, 'GET', '/api/user/preferences', token: $token)['table']);
    }

    public function testLaTableEnregistreeSurvitAuRecalculEtAuxGouts(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'tablekeep@example.test');
        $this->call($client, 'PUT', '/api/user/table', ['players' => 3, 'maxTime' => 30], $token);

        $this->call($client, 'POST', '/api/user/preferences/recompute', token: $token);
        $this->call($client, 'PUT', '/api/user/taste', ['families' => ['auction']], $token);

        $prefs = $this->call($client, 'GET', '/api/user/preferences', token: $token);
        self::assertSame(['players' => 3, 'maxTime' => 30], $prefs['table']);
        self::assertSame(['auction'], $prefs['declaredFamilies']);
    }

    public function testLExportDesDonneesInclutLesGoutsEtLaTable(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'exportgouts@example.test');
        $this->call($client, 'PUT', '/api/user/taste', ['families' => ['cooperation']], $token);
        $this->call($client, 'PUT', '/api/user/table', ['players' => 5, 'maxTime' => 45], $token);

        $export = $this->call($client, 'GET', '/api/user/export', token: $token);

        self::assertSame('["cooperation"]', $export['preferences']['declaredFamilies']);
        self::assertSame(5, $export['preferences']['tablePlayers']);
        self::assertSame(45, $export['preferences']['tableMaxTime']);
    }

    public function testLaTableRefuseLesValeursInvalides(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'tablebad@example.test');

        foreach ([
            ['players' => 0, 'maxTime' => 60],
            ['players' => 9, 'maxTime' => 60],
            ['players' => '4', 'maxTime' => 60],
            ['players' => 4, 'maxTime' => 10],
            ['players' => 4, 'maxTime' => 250],
            ['players' => 4, 'maxTime' => 50],
            ['players' => 4],
            [],
        ] as $corps) {
            $this->call($client, 'PUT', '/api/user/table', $corps, $token);
            self::assertResponseStatusCodeSame(400);
        }

        $this->call($client, 'PUT', '/api/user/table', ['players' => 4, 'maxTime' => 60]);
        self::assertResponseStatusCodeSame(401);
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
