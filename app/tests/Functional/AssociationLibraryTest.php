<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class AssociationLibraryTest extends ApiTestCase
{
    public function testUnAdministrateurRenseigneLaLudothequeDeLAssociationParMyLudo(): void
    {
        $client = static::createClient();
        $token = $this->admin($client, 'admin-asso@example.test');
        $a = $this->jeu('Zzasso A');
        $b = $this->jeu('Zzasso B');

        $bilan = $this->call($client, 'POST', '/api/myludo/import', [
            'target' => 'association',
            'items'  => [['gameId' => $a, 'rating' => 9], ['gameId' => $b]],
        ], $token);

        self::assertResponseIsSuccessful();
        self::assertSame(['total' => 2, 'added' => 2, 'existing' => 0, 'rated' => 0, 'target' => 'association'], $bilan);
        self::assertSame(2, $this->compter('SELECT COUNT(*) FROM association_game WHERE game_id IN (?, ?)', [$a, $b]));
        // Rien n'entre dans la ludothèque personnelle de l'administrateur.
        self::assertSame(0, $this->compter('SELECT COUNT(*) FROM user_game WHERE user_id = (SELECT id FROM app_user WHERE email = ?)', ['admin-asso@example.test']));

        // Un second passage ne duplique rien.
        $encore = $this->call($client, 'POST', '/api/myludo/import', ['target' => 'association', 'items' => [['gameId' => $a]]], $token);
        self::assertSame(['added' => 0, 'existing' => 1], ['added' => $encore['added'], 'existing' => $encore['existing']]);
    }

    public function testUnJoueurNePeutPasRenseignerLaLudothequeDeLAssociation(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'membre-asso@example.test');
        $a = $this->jeu('Zzasso Refus');

        $this->call($client, 'POST', '/api/myludo/import', ['target' => 'association', 'items' => [['gameId' => $a]]], $token);
        self::assertResponseStatusCodeSame(403);
        $this->call($client, 'POST', '/api/bgg/import', ['target' => 'association', 'username' => 'asso'], $token);
        self::assertResponseStatusCodeSame(403);

        self::assertSame(0, $this->compter('SELECT COUNT(*) FROM association_game WHERE game_id = ?', [$a]));
    }

    public function testLaDestinationDoitEtreConnue(): void
    {
        $client = static::createClient();
        $token = $this->admin($client, 'admin-cible@example.test');

        $this->call($client, 'POST', '/api/myludo/import', ['target' => 'ailleurs', 'items' => [['gameId' => 1]]], $token);
        self::assertResponseStatusCodeSame(400);
        $this->call($client, 'POST', '/api/bgg/import', ['target' => 'ailleurs', 'username' => 'asso'], $token);
        self::assertResponseStatusCodeSame(400);
    }

    public function testLImportBggDeLAssociationExigeLePseudoDeLAssociation(): void
    {
        $client = static::createClient();
        $token = $this->admin($client, 'admin-bgg@example.test');
        // Le pseudo BGG de l'administrateur ne doit jamais servir de repli pour la ludothèque commune.
        static::getContainer()->get(Connection::class)->executeStatement("UPDATE app_user SET bgg_username = 'pseudo-perso' WHERE email = 'admin-bgg@example.test'");

        $this->call($client, 'POST', '/api/bgg/import', ['target' => 'association'], $token);

        self::assertResponseStatusCodeSame(400);
    }

    public function testLAdministrateurListeEtRetireUnJeu(): void
    {
        $client = static::createClient();
        $token = $this->admin($client, 'admin-liste@example.test');
        $a = $this->jeu('Zzasso Liste');
        $this->call($client, 'POST', '/api/myludo/import', ['target' => 'association', 'items' => [['gameId' => $a]]], $token);

        $liste = $this->call($client, 'GET', '/api/admin/association/games', token: $token);
        self::assertResponseIsSuccessful();
        self::assertSame(['Zzasso Liste'], array_column($liste['games'], 'name'));
        self::assertSame(1, $liste['count']);

        $this->call($client, 'DELETE', "/api/admin/association/games/$a", token: $token);
        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->compter('SELECT COUNT(*) FROM association_game WHERE game_id = ?', [$a]));

        $this->call($client, 'DELETE', "/api/admin/association/games/$a", token: $token);
        self::assertResponseStatusCodeSame(404);
    }

    private function admin(KernelBrowser $client, string $email): string
    {
        $token = $this->register($client, $email);
        static::getContainer()->get(Connection::class)->executeStatement('UPDATE app_user SET is_admin = TRUE WHERE email = ?', [$email]);

        return $token;
    }

    private function jeu(string $nom): int
    {
        $conn = static::getContainer()->get(Connection::class);
        $conn->executeStatement(
            "INSERT INTO game (bgg_id, name, min_players, max_players, playing_time, complexity, categories, mechanics, year_published, is_expansion, mechanic_families, created_at)
             VALUES (?, ?, 2, 4, 30, 2, '[]', '[]', 2020, FALSE, '[]', NOW())",
            ['zzas' . md5($nom), $nom],
        );

        return (int) $conn->lastInsertId();
    }

    /** @param list<int|string> $params */
    private function compter(string $sql, array $params): int
    {
        return (int) static::getContainer()->get(Connection::class)->fetchOne($sql, $params);
    }
}
