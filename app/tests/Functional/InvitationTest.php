<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\InvitationCode;
use App\Enum\UserRole;
use App\Repository\InvitationCodeRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class InvitationTest extends ApiTestCase
{
    protected function tearDown(): void
    {
        $this->requireCode(false);
        parent::tearDown();
    }

    public function testSansCodeLInscriptionEstRefuseeQuandOnLExige(): void
    {
        $this->requireCode(true);
        $client = static::createClient();

        $reponse = $this->call($client, 'POST', '/api/auth/register', ['email' => 'sans-code@example.test', 'password' => 'mot-de-passe-solide']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame("Code d'invitation invalide ou expiré.", $reponse['error']);
    }

    public function testLeFormulaireSaitQueLeCodeEstFacultatifEnInscriptionLibre(): void
    {
        $client = static::createClient();

        self::assertFalse($this->call($client, 'GET', '/api/auth/registration')['requiresCode']);
    }

    public function testLeFormulaireSaitQueLeCodeEstExige(): void
    {
        $this->requireCode(true);
        $client = static::createClient();

        self::assertTrue($this->call($client, 'GET', '/api/auth/registration')['requiresCode']);
    }

    public function testUnCodeValideCreeUnMembreEtSeConsomme(): void
    {
        $this->requireCode(true);
        $client = static::createClient();
        $code = $this->creerCode('Soirée de rentrée', UserRole::Member, 1);

        $token = $this->inscrire($client, 'invite@example.test', strtolower(InvitationCodeRepository::format($code)));
        $moi = $this->call($client, 'GET', '/api/auth/me', token: $token);

        self::assertSame('member', $moi['role']);
        self::assertSame(1, $this->usages($code));
    }

    public function testLeCodeAccordeLeRoleVip(): void
    {
        $this->requireCode(true);
        $client = static::createClient();
        $code = $this->creerCode('Partenaire', UserRole::Vip, 1);

        $token = $this->inscrire($client, 'vip@example.test', $code);

        self::assertSame('vip', $this->call($client, 'GET', '/api/auth/me', token: $token)['role']);
    }

    public function testUnCodeEpuiseExpireOuRevoqueEstRefuse(): void
    {
        $this->requireCode(true);
        $client = static::createClient();

        $epuise = $this->creerCode('Un seul usage', UserRole::Member, 1);
        $this->inscrire($client, 'premier@example.test', $epuise);
        $this->refuser($client, 'second@example.test', $epuise);

        $expire = $this->creerCode('Périmé', UserRole::Member, 5);
        static::getContainer()->get(Connection::class)->executeStatement("UPDATE invitation_code SET expires_at = NOW() - INTERVAL '1 hour' WHERE code = ?", [$expire]);
        $this->refuser($client, 'tard@example.test', $expire);

        $revoque = $this->creerCode('Annulé', UserRole::Member, 5);
        static::getContainer()->get(Connection::class)->executeStatement('UPDATE invitation_code SET revoked_at = NOW() WHERE code = ?', [$revoque]);
        $this->refuser($client, 'annule@example.test', $revoque);

        $this->refuser($client, 'inconnu@example.test', 'ZZZZZZZZZZ');
    }

    public function testUneInscriptionEnEchecNUsePasLeCode(): void
    {
        $this->requireCode(true);
        $client = static::createClient();
        $code = $this->creerCode('Soirée', UserRole::Member, 2);
        $this->inscrire($client, 'doublon@example.test', $code);

        $this->call($client, 'POST', '/api/auth/register', ['email' => 'doublon@example.test', 'password' => 'mot-de-passe-solide', 'code' => $code]);

        self::assertResponseStatusCodeSame(409);
        self::assertSame(1, $this->usages($code));
    }

    public function testLeDernierUsageNeServQuUneFois(): void
    {
        static::bootKernel();
        $depot = static::getContainer()->get(InvitationCodeRepository::class);
        $code = $this->creerCode('Dernier usage', UserRole::Member, 1);

        self::assertNotNull($depot->consume($code));
        self::assertNull($depot->consume($code));
    }

    public function testEnInscriptionLibreLeCodeResteFacultatifMaisSiFourniDoitEtreBon(): void
    {
        $client = static::createClient();

        $this->register($client, 'libre@example.test');
        self::assertResponseIsSuccessful();

        $this->refuser($client, 'faux-code@example.test', 'ZZZZZZZZZZ');
    }

    public function testLAdministrationCreeListeEtRevoqueLesCodes(): void
    {
        $client = static::createClient();
        $token = $this->tokenAdmin($client);

        $cree = $this->call($client, 'POST', '/api/admin/invitations', ['label' => 'Pour Camille', 'role' => 'vip', 'maxUses' => 3], $token);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('vip', $cree['role']);
        self::assertSame('active', $cree['status']);
        self::assertMatchesRegularExpression('/^[A-Z2-9]{5}-[A-Z2-9]{5}$/', $cree['code']);

        $liste = $this->call($client, 'GET', '/api/admin/invitations', token: $token);
        self::assertContains('Pour Camille', array_column($liste, 'label'));

        $revoque = $this->call($client, 'POST', '/api/admin/invitations/'.$cree['id'].'/revoke', token: $token);
        self::assertSame('revoked', $revoque['status']);

        $this->call($client, 'POST', '/api/admin/invitations', ['label' => '', 'maxUses' => 1], $token);
        self::assertResponseStatusCodeSame(422);
        $this->call($client, 'POST', '/api/admin/invitations', ['label' => 'x', 'maxUses' => 0], $token);
        self::assertResponseStatusCodeSame(422);
    }

    public function testLAdministrationListeLesComptesEtChangeLeurRole(): void
    {
        $client = static::createClient();
        $token = $this->tokenAdmin($client);
        $this->register($client, 'cible@example.test');
        $id = (int) static::getContainer()->get(Connection::class)->fetchOne("SELECT id FROM app_user WHERE email = 'cible@example.test'");

        $liste = $this->call($client, 'GET', '/api/admin/users?q=cible', token: $token);
        self::assertSame(1, $liste['total']);
        self::assertSame('member', $liste['items'][0]['role']);

        $maj = $this->call($client, 'PATCH', "/api/admin/users/$id", ['role' => 'vip'], $token);
        self::assertResponseIsSuccessful();
        self::assertSame('vip', $maj['role']);

        $vips = $this->call($client, 'GET', '/api/admin/users?role=vip&q=cible', token: $token);
        self::assertSame(1, $vips['total']);

        $this->call($client, 'PATCH', "/api/admin/users/$id", ['role' => 'roi'], $token);
        self::assertResponseStatusCodeSame(422);
    }

    public function testUnAdminNePeutPasChangerSonPropreRole(): void
    {
        $client = static::createClient();
        $token = $this->tokenAdmin($client);
        $id = (int) static::getContainer()->get(Connection::class)->fetchOne("SELECT id FROM app_user WHERE email = 'admin-roles@example.test'");

        $this->call($client, 'PATCH', "/api/admin/users/$id", ['role' => 'vip'], $token);

        self::assertResponseStatusCodeSame(422);
    }

    private function requireCode(bool $required): void
    {
        $_SERVER['REGISTRATION_REQUIRES_CODE'] = $_ENV['REGISTRATION_REQUIRES_CODE'] = $required ? '1' : '0';
    }

    private function creerCode(string $label, UserRole $role, int $usages): string
    {
        static::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $code = new InvitationCode(static::getContainer()->get(InvitationCodeRepository::class)->generateCode(), $label, $role, $usages);
        $em->persist($code);
        $em->flush();

        return $code->getCode();
    }

    private function usages(string $code): int
    {
        return (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT used_count FROM invitation_code WHERE code = ?', [$code]);
    }

    private function inscrire(KernelBrowser $client, string $email, string $code): string
    {
        $this->call($client, 'POST', '/api/auth/register', ['email' => $email, 'password' => 'mot-de-passe-solide', 'code' => $code]);
        self::assertResponseStatusCodeSame(201);

        // La session vient avec la confirmation de l'adresse : on la confirme, puis on se connecte.
        static::getContainer()->get(Connection::class)->executeStatement('UPDATE app_user SET email_verified_at = NOW() WHERE email = ?', [$email]);

        return $this->call($client, 'POST', '/api/auth/login', ['email' => $email, 'password' => 'mot-de-passe-solide'])['token'];
    }

    private function refuser(KernelBrowser $client, string $email, string $code): void
    {
        $this->call($client, 'POST', '/api/auth/register', ['email' => $email, 'password' => 'mot-de-passe-solide', 'code' => $code]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM app_user WHERE email = ?', [$email]));
    }

    private function tokenAdmin(KernelBrowser $client): string
    {
        $token = $this->register($client, 'admin-roles@example.test');
        static::getContainer()->get(Connection::class)->executeStatement("UPDATE app_user SET is_admin = TRUE WHERE email = 'admin-roles@example.test'");

        return $token;
    }
}
