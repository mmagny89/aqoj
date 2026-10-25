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
    private function creerJeu(string $nom, int $annee, float $complexite, float $note, int $votes, array $familles = [], bool $extension = false): void
    {
        static::getContainer()->get(Connection::class)->executeStatement(
            "INSERT INTO game (bgg_id, name, min_players, max_players, playing_time, complexity, categories, mechanics,
                               year_published, rating_bgg, users_rated, is_expansion, mechanic_families, created_at)
             VALUES (?, ?, 2, 4, 45, ?, '[]', '[\"Dice Rolling\"]', ?, ?, ?, ?, ?, NOW())",
            [
                'zz' . md5($nom), $nom, $complexite, $annee, $note, $votes,
                $extension ? 't' : 'f', json_encode($familles),
            ],
        );
    }
}
