<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;

final class GameFeedbackTest extends ApiTestCase
{
    public function testUnJeuEcarteNEstPlusPropose(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'ecarte@example.test');
        $annee = (int) date('Y');
        $a = $this->creerJeu('Zzfb Nouveau A', $annee, 7.5, 2000);
        $this->creerJeu('Zzfb Nouveau B', $annee, 7.4, 1900);
        $c = $this->creerJeu('Zzfb Classique', $annee - 8, 8.2, 20000);

        $noms = fn() => array_column(array_column($this->call($client, 'GET', '/api/games/home-reco', token: $token)['recent'], 'game'), 'name');
        self::assertEqualsCanonicalizing(['Zzfb Nouveau A', 'Zzfb Nouveau B'], $noms());
        $decouverte = fn() => array_column(array_column($this->call($client, 'GET', '/api/games/recommendation?scope=discover', token: $token)['items'], 'game'), 'name');
        self::assertContains('Zzfb Classique', $decouverte());

        $this->call($client, 'PUT', "/api/games/$a/feedback", ['verdict' => 'not_interested'], $token);
        self::assertResponseIsSuccessful();
        $this->call($client, 'PUT', "/api/games/$c/feedback", ['verdict' => 'played_meh'], $token);

        self::assertSame(['Zzfb Nouveau B'], $noms());
        self::assertNotContains('Zzfb Classique', $decouverte());

        // Un retour se change et s'annule : le jeu revient.
        $this->call($client, 'DELETE', "/api/games/$a/feedback", token: $token);
        self::assertResponseStatusCodeSame(204);
        self::assertEqualsCanonicalizing(['Zzfb Nouveau A', 'Zzfb Nouveau B'], $noms());
    }

    public function testLesRetoursSontPersonnelsEtValides(): void
    {
        $client = static::createClient();
        $moi = $this->register($client, 'moi-fb@example.test');
        $autre = $this->register($client, 'autre-fb@example.test');
        $id = $this->creerJeu('Zzfb Partage', (int) date('Y'), 7.5, 2000);

        $this->call($client, 'PUT', "/api/games/$id/feedback", ['verdict' => 'trop-cher'], $moi);
        self::assertResponseStatusCodeSame(400);
        $this->call($client, 'PUT', '/api/games/999999999/feedback', ['verdict' => 'not_interested'], $moi);
        self::assertResponseStatusCodeSame(404);
        $this->call($client, 'PUT', "/api/games/$id/feedback", ['verdict' => 'not_interested']);
        self::assertResponseStatusCodeSame(401);

        $this->call($client, 'PUT', "/api/games/$id/feedback", ['verdict' => 'not_interested'], $moi);
        $noms = fn(string $t) => array_column(array_column($this->call($client, 'GET', '/api/games/home-reco', token: $t)['recent'], 'game'), 'name');
        self::assertSame([], $noms($moi));
        self::assertSame(['Zzfb Partage'], $noms($autre));
    }

    public function testLaPropositionDitPourquoi(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'pourquoi@example.test');
        $aime = $this->creerJeu('Zzfb Aime', 2018, 7.8, 5000, ['deck_building', 'cooperation']);
        $this->creerJeu('Zzfb Propose', (int) date('Y'), 7.5, 2000, ['deck_building', 'cooperation']);
        $this->creerJeu('Zzfb Sans Rapport', (int) date('Y'), 7.4, 1900, ['auction']);
        static::getContainer()->get(Connection::class)->executeStatement(
            'INSERT INTO user_game (user_id, game_id, bgg_user_rating) VALUES ((SELECT id FROM app_user WHERE email = ?), ?, 9)',
            ['pourquoi@example.test', $aime],
        );

        $items = $this->call($client, 'GET', '/api/games/home-reco', token: $token)['recent'];
        $parNom = [];
        foreach ($items as $r) {
            $parNom[$r['game']['name']] = $r['why'];
        }

        self::assertSame(['id' => $aime, 'name' => 'Zzfb Aime'], $parNom['Zzfb Propose']['liked']);
        self::assertEqualsCanonicalizing(['deck_building', 'cooperation'], $parNom['Zzfb Propose']['families']);
        // « auction » n'est pas un goût du joueur : aucune mécanique à citer.
        self::assertSame([], $parNom['Zzfb Sans Rapport']['families'] ?? []);
    }

    public function testTroisJeuxBofRetirentUneFamilleDuProfilDeduit(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'bof@example.test');
        $conn = static::getContainer()->get(Connection::class);
        $uid = (int) $conn->fetchOne('SELECT id FROM app_user WHERE email = ?', ['bof@example.test']);

        foreach ([1, 2, 3] as $i) {
            $id = $this->creerJeu("Zzfb Bof $i", 2015, 7.0, 3000, ['auction', 'deck_building']);
            $conn->executeStatement('INSERT INTO user_game (user_id, game_id, bgg_user_rating) VALUES (?, ?, 8)', [$uid, $id]);
        }
        self::assertEqualsCanonicalizing(['auction', 'deck_building'], $this->call($client, 'GET', '/api/games/home-reco', token: $token)['families']);

        $ids = $conn->fetchFirstColumn("SELECT id FROM game WHERE name LIKE 'Zzfb Bof %'");
        foreach ($ids as $id) {
            $this->call($client, 'PUT', "/api/games/$id/feedback", ['verdict' => 'played_meh'], $token);
        }

        self::assertSame([], $this->call($client, 'GET', '/api/games/home-reco', token: $token)['families']);
    }

    public function testJAimeraisJouerResteProposeEtJAiJoueEtJAimeEstEcarteMaisCompteCommeUnGout(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'positifs@example.test');
        $annee = (int) date('Y');
        $envie = $this->creerJeu('Zzfb Envie', $annee, 7.5, 2000, ['cooperation']);
        $joue = $this->creerJeu('Zzfb Joue Aime', $annee, 7.4, 1900, ['deck_building']);

        $reco = fn() => $this->call($client, 'GET', '/api/games/home-reco', token: $token);
        $parNom = function (array $items): array {
            $r = [];
            foreach ($items as $i) {
                $r[$i['game']['name']] = $i;
            }

            return $r;
        };

        $this->call($client, 'PUT', "/api/games/$envie/feedback", ['verdict' => 'want_to_play'], $token);
        self::assertResponseIsSuccessful();
        $this->call($client, 'PUT', "/api/games/$joue/feedback", ['verdict' => 'played_liked'], $token);
        self::assertResponseIsSuccessful();

        $vu = $reco();
        $items = $parNom($vu['recent']);
        // L'envie et le jeu aimé restent proposés, avec leur retour : seuls « pas intéressé » et « bof » écartent.
        self::assertSame('want_to_play', $items['Zzfb Envie']['feedback']);
        self::assertSame('played_liked', $items['Zzfb Joue Aime']['feedback']);
        // Les deux comptent comme des goûts : le profil n'est plus vide.
        self::assertEqualsCanonicalizing(['cooperation', 'deck_building'], $vu['families']);
        self::assertFalse($vu['coldStart']);
    }

    public function testUnePartieAnnuleLEnvieDeJouerEtLaListeLesRend(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'envies@example.test');
        $a = $this->creerJeu('Zzfb Envie A', 2020, 7.5, 2000);
        $b = $this->creerJeu('Zzfb Envie B', 2021, 7.5, 2000);
        $c = $this->creerJeu('Zzfb Pas Envie', 2022, 7.5, 2000);

        $this->call($client, 'PUT', "/api/games/$a/feedback", ['verdict' => 'want_to_play'], $token);
        $this->call($client, 'PUT', "/api/games/$b/feedback", ['verdict' => 'want_to_play'], $token);
        $this->call($client, 'PUT', "/api/games/$c/feedback", ['verdict' => 'not_interested'], $token);

        $liste = $this->call($client, 'GET', '/api/user/wishlist', token: $token);
        self::assertEqualsCanonicalizing(['Zzfb Envie A', 'Zzfb Envie B'], array_column(array_column($liste, 'game'), 'name'));
        self::assertSame(['want_to_play', 'want_to_play'], array_column($liste, 'feedback'));

        // Une partie saisie sur A : l'envie est satisfaite.
        $this->call($client, 'POST', '/api/sessions', ['gameId' => $a, 'playersCount' => 3, 'playedAt' => date('Y-m-d')], $token);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(['Zzfb Envie B'], array_column(array_column($this->call($client, 'GET', '/api/user/wishlist', token: $token), 'game'), 'name'));

        // La partie d'un autre joueur n'y change rien.
        $autre = $this->register($client, 'autre-envie@example.test');
        $this->call($client, 'POST', '/api/sessions', ['gameId' => $b, 'playersCount' => 2, 'playedAt' => date('Y-m-d')], $autre);
        self::assertSame(['Zzfb Envie B'], array_column(array_column($this->call($client, 'GET', '/api/user/wishlist', token: $token), 'game'), 'name'));

        $this->call($client, 'GET', '/api/user/wishlist');
        self::assertResponseStatusCodeSame(401);
    }

    public function testLesPartiesEtLesNotesCochentAutomatiquementLeRetour(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'auto@example.test');
        $conn = static::getContainer()->get(Connection::class);
        $uid = (int) $conn->fetchOne('SELECT id FROM app_user WHERE email = ?', ['auto@example.test']);
        $aime = $this->creerJeu('Zzfb Auto Aime', 2020, 7.0, 500);
        $bof = $this->creerJeu('Zzfb Auto Bof', 2020, 7.0, 500);
        $moyen = $this->creerJeu('Zzfb Auto Moyen', 2020, 7.0, 500);
        $sansPartie = $this->creerJeu('Zzfb Auto Sans Partie', 2020, 7.0, 500);
        $partieAimee = $this->creerJeu('Zzfb Auto Partie Aimee', 2020, 7.0, 500);
        $note = fn(int $id, ?float $n) => $conn->executeStatement('INSERT INTO user_game (user_id, game_id, bgg_user_rating) VALUES (?, ?, ?)', [$uid, $id, $n]);
        $partie = fn(int $id, ?int $rating) => $this->call($client, 'POST', '/api/sessions', ['gameId' => $id, 'playersCount' => 3, 'playedAt' => date('Y-m-d'), 'rating' => $rating], $token);
        $retour = fn(int $id) => $conn->fetchAssociative('SELECT verdict, source FROM game_feedback WHERE user_id = ? AND game_id = ?', [$uid, $id]) ?: null;

        $note($aime, 8.0);
        $note($bof, 4.0);
        $note($moyen, 6.0);
        $note($sansPartie, 9.0);
        // La note seule ne dit pas qu'on y a joué : rien tant qu'il n'y a pas de partie.
        self::assertNull($retour($aime));
        self::assertNull($retour($sansPartie));

        $partie($aime, null);
        $partie($bof, null);
        $partie($moyen, null);
        self::assertSame(['verdict' => 'played_liked', 'source' => 'auto'], $retour($aime));
        self::assertSame(['verdict' => 'played_meh', 'source' => 'auto'], $retour($bof));
        self::assertNull($retour($moyen));
        self::assertNull($retour($sansPartie));

        // Sans note personnelle, c'est la partie notée qui décide (5 = aimée).
        $partie($partieAimee, 5);
        self::assertSame(['verdict' => 'played_liked', 'source' => 'auto'], $retour($partieAimee));

        // La note change : le retour automatique suit ; sans signal il disparaît.
        $conn->executeStatement('UPDATE user_game SET bgg_user_rating = 3 WHERE user_id = ? AND game_id = ?', [$uid, $aime]);
        self::assertSame('played_meh', $retour($aime)['verdict']);
        $conn->executeStatement('UPDATE user_game SET bgg_user_rating = 6 WHERE user_id = ? AND game_id = ?', [$uid, $aime]);
        self::assertNull($retour($aime));

        // Un retour automatique coche seulement : le jeu reste proposé (il n'est pas dans la ludothèque du joueur).
        $propose = array_column(array_column($this->call($client, 'GET', '/api/games/recommendation?scope=discover', token: $token)['items'], 'game'), 'name');
        self::assertContains('Zzfb Auto Partie Aimee', $propose);
    }

    public function testLeChoixDuJoueurLEmporteSurLeRetourAutomatique(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'manuel@example.test');
        $conn = static::getContainer()->get(Connection::class);
        $uid = (int) $conn->fetchOne('SELECT id FROM app_user WHERE email = ?', ['manuel@example.test']);
        $id = $this->creerJeu('Zzfb Manuel', 2020, 7.0, 500);
        $conn->executeStatement('INSERT INTO user_game (user_id, game_id, bgg_user_rating) VALUES (?, ?, 9)', [$uid, $id]);

        $this->call($client, 'PUT', "/api/games/$id/feedback", ['verdict' => 'not_interested'], $token);
        $this->call($client, 'POST', '/api/sessions', ['gameId' => $id, 'playersCount' => 2, 'playedAt' => date('Y-m-d'), 'rating' => 5], $token);

        self::assertSame(['verdict' => 'not_interested', 'source' => 'user'], $conn->fetchAssociative('SELECT verdict, source FROM game_feedback WHERE user_id = ? AND game_id = ?', [$uid, $id]));

        // Et la suppression de la partie retire un retour automatique, pas un choix manuel.
        $autre = $this->creerJeu('Zzfb Manuel Auto', 2020, 7.0, 500);
        $conn->executeStatement('INSERT INTO user_game (user_id, game_id, bgg_user_rating) VALUES (?, ?, 9)', [$uid, $autre]);
        $partie = $this->call($client, 'POST', '/api/sessions', ['gameId' => $autre, 'playersCount' => 2, 'playedAt' => date('Y-m-d')], $token);
        self::assertSame('auto', $conn->fetchOne('SELECT source FROM game_feedback WHERE user_id = ? AND game_id = ?', [$uid, $autre]));
        $this->call($client, 'DELETE', '/api/sessions/' . $partie['id'], token: $token);
        self::assertFalse($conn->fetchOne('SELECT source FROM game_feedback WHERE user_id = ? AND game_id = ?', [$uid, $autre]));
    }

    /** @param list<string> $familles */
    private function creerJeu(string $nom, int $annee, float $note, int $votes, array $familles = []): int
    {
        $conn = static::getContainer()->get(Connection::class);
        $conn->executeStatement(
            "INSERT INTO game (bgg_id, name, min_players, max_players, playing_time, complexity, categories, mechanics,
                               year_published, rating_bgg, users_rated, is_expansion, mechanic_families, created_at)
             VALUES (?, ?, 2, 4, 45, 2.3, '[]', '[\"Dice Rolling\"]', ?, ?, ?, false, ?, NOW())",
            ['zz' . md5($nom), $nom, $annee, $note, $votes, json_encode($familles)],
        );

        return (int) $conn->lastInsertId();
    }
}
