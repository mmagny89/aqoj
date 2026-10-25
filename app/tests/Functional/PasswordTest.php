<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Firebase\JWT\JWT;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Mime\Email;

final class PasswordTest extends ApiTestCase
{
    private const PASSWORD = 'mot-de-passe-solide';

    public function testMotDePasseOublieRepondLaMemeChoseQueLeCompteExisteOuNon(): void
    {
        $client = static::createClient();
        $this->register($client, 'connu@example.test');
        self::assertEmailCount(0);

        // Les assertions de courrier portent sur la dernière requête : on les lit juste après chacune.
        $connu = $this->call($client, 'POST', '/api/auth/forgot-password', ['email' => 'connu@example.test']);
        self::assertResponseStatusCodeSame(202);
        self::assertEmailCount(1);
        self::assertEmailAddressContains(self::getMailerMessage(0), 'To', 'connu@example.test');

        $inconnu = $this->call($client, 'POST', '/api/auth/forgot-password', ['email' => 'inconnu@example.test']);
        self::assertResponseStatusCodeSame(202);
        self::assertEmailCount(0);
        self::assertSame($connu, $inconnu);
    }

    public function testLeLienDeReinitialisationChangeLeMotDePasseUneSeuleFois(): void
    {
        $client = static::createClient();
        $this->register($client, 'oubli@example.test');
        $this->call($client, 'POST', '/api/auth/forgot-password', ['email' => 'oubli@example.test']);
        $jeton = $this->jetonDuMail();

        $this->call($client, 'POST', '/api/auth/reset-password', ['token' => $jeton, 'password' => 'nouveau-mot-de-passe']);
        self::assertResponseIsSuccessful();

        $this->call($client, 'POST', '/api/auth/login', ['email' => 'oubli@example.test', 'password' => 'nouveau-mot-de-passe']);
        self::assertResponseIsSuccessful();
        $this->call($client, 'POST', '/api/auth/login', ['email' => 'oubli@example.test', 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(401);

        $this->call($client, 'POST', '/api/auth/reset-password', ['token' => $jeton, 'password' => 'encore-un-autre-mot']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testUnLienExpireOuInconnuOuUnMotDePasseCourtSontRefuses(): void
    {
        $client = static::createClient();
        $this->register($client, 'expire@example.test');
        $this->call($client, 'POST', '/api/auth/forgot-password', ['email' => 'expire@example.test']);
        $jeton = $this->jetonDuMail();

        $this->call($client, 'POST', '/api/auth/reset-password', ['token' => $jeton, 'password' => 'court']);
        self::assertResponseStatusCodeSame(422);

        static::getContainer()->get(Connection::class)->executeStatement("UPDATE password_reset_request SET expires_at = NOW() - INTERVAL '1 minute'");
        $reponse = $this->call($client, 'POST', '/api/auth/reset-password', ['token' => $jeton, 'password' => 'nouveau-mot-de-passe']);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('invalide ou expiré', $reponse['error']);

        $this->call($client, 'POST', '/api/auth/reset-password', ['token' => 'nimportequoi', 'password' => 'nouveau-mot-de-passe']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testUneNouvelleDemandeAnnuleLaPrecedente(): void
    {
        $client = static::createClient();
        $this->register($client, 'deux@example.test');
        $this->call($client, 'POST', '/api/auth/forgot-password', ['email' => 'deux@example.test']);
        $premier = $this->jetonDuMail();
        $this->call($client, 'POST', '/api/auth/forgot-password', ['email' => 'deux@example.test']);
        $second = $this->jetonDuMail();

        $this->call($client, 'POST', '/api/auth/reset-password', ['token' => $premier, 'password' => 'nouveau-mot-de-passe']);
        self::assertResponseStatusCodeSame(422);
        $this->call($client, 'POST', '/api/auth/reset-password', ['token' => $second, 'password' => 'nouveau-mot-de-passe']);
        self::assertResponseIsSuccessful();
    }

    public function testLaReinitialisationRevoqueLesSessionsAnterieures(): void
    {
        $client = static::createClient();
        $this->register($client, 'session@example.test');
        $ancien = $this->jetonAncien('session@example.test');
        $this->call($client, 'GET', '/api/auth/me', token: $ancien);
        self::assertResponseIsSuccessful();

        $this->call($client, 'POST', '/api/auth/forgot-password', ['email' => 'session@example.test']);
        $this->call($client, 'POST', '/api/auth/reset-password', ['token' => $this->jetonDuMail(), 'password' => 'nouveau-mot-de-passe']);

        $this->call($client, 'GET', '/api/auth/me', token: $ancien);
        self::assertResponseStatusCodeSame(401);
    }

    public function testLesDemandesRepeteesSontLimitees(): void
    {
        $client = static::createClient();

        for ($i = 0; $i < 5; $i++) {
            $this->call($client, 'POST', '/api/auth/forgot-password', ['email' => "cible{$i}@example.test"]);
            self::assertResponseStatusCodeSame(202);
        }
        $this->call($client, 'POST', '/api/auth/forgot-password', ['email' => 'cible6@example.test']);

        self::assertResponseStatusCodeSame(429);
    }

    public function testLeChangementDeMotDePasseExigeLActuelEtGardeLaSessionOuverte(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'change@example.test');
        $ancien = $this->jetonAncien('change@example.test');

        $this->call($client, 'PATCH', '/api/user/password', ['currentPassword' => 'faux-mot-de-passe', 'newPassword' => 'nouveau-mot-de-passe'], $token);
        self::assertResponseStatusCodeSame(403);
        $this->call($client, 'PATCH', '/api/user/password', ['currentPassword' => self::PASSWORD, 'newPassword' => 'court'], $token);
        self::assertResponseStatusCodeSame(422);
        $this->call($client, 'PATCH', '/api/user/password', ['currentPassword' => self::PASSWORD, 'newPassword' => self::PASSWORD], $token);
        self::assertResponseStatusCodeSame(422);

        $reponse = $this->call($client, 'PATCH', '/api/user/password', ['currentPassword' => self::PASSWORD, 'newPassword' => 'nouveau-mot-de-passe'], $token);
        self::assertResponseIsSuccessful();
        self::assertEmailCount(1);

        $this->call($client, 'GET', '/api/auth/me', token: $reponse['token']);
        self::assertResponseIsSuccessful();
        $this->call($client, 'GET', '/api/auth/me', token: $ancien);
        self::assertResponseStatusCodeSame(401);
        $this->call($client, 'POST', '/api/auth/login', ['email' => 'change@example.test', 'password' => 'nouveau-mot-de-passe']);
        self::assertResponseIsSuccessful();
    }

    public function testLeChangementDeMotDePasseExigeUneSession(): void
    {
        $client = static::createClient();

        $this->call($client, 'PATCH', '/api/user/password', ['currentPassword' => 'x', 'newPassword' => 'y']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testLeNumeroDAdherentSeNormaliseEtSeRetireOuSeRefuse(): void
    {
        $client = static::createClient();
        $token = $this->register($client, 'adherent@example.test');

        foreach (['edj 123' => 'EDJ-0123', 'EDJ-0042' => 'EDJ-0042', '7' => 'EDJ-0007', ' edj-12345 ' => 'EDJ-12345'] as $saisi => $attendu) {
            $moi = $this->call($client, 'PATCH', '/api/user/member-number', ['memberNumber' => $saisi], $token);
            self::assertResponseIsSuccessful();
            self::assertSame($attendu, $moi['memberNumber']);
        }

        $this->call($client, 'PATCH', '/api/user/member-number', ['memberNumber' => 'ABC-12'], $token);
        self::assertResponseStatusCodeSame(422);

        $efface = $this->call($client, 'PATCH', '/api/user/member-number', ['memberNumber' => ''], $token);
        self::assertNull($efface['memberNumber']);
        self::assertNull($this->call($client, 'GET', '/api/auth/me', token: $token)['memberNumber']);
    }

    public function testLAdministrationVoitEtChercheLeNumeroDAdherent(): void
    {
        $client = static::createClient();
        $adherent = $this->register($client, 'cherche@example.test');
        $this->call($client, 'PATCH', '/api/user/member-number', ['memberNumber' => '777'], $adherent);
        $admin = $this->register($client, 'chef@example.test');
        static::getContainer()->get(Connection::class)->executeStatement("UPDATE app_user SET is_admin = TRUE WHERE email = 'chef@example.test'");

        $liste = $this->call($client, 'GET', '/api/admin/users?q=EDJ-0777', token: $admin);

        self::assertSame(1, $liste['total']);
        self::assertSame('EDJ-0777', $liste['items'][0]['memberNumber']);
    }

    /** Le jeton en clair, lu dans le lien du n-ième e-mail envoyé. */
    private function jetonDuMail(int $index = 0): string
    {
        $mail = self::getMailerMessage($index);
        self::assertInstanceOf(Email::class, $mail);
        self::assertSame(1, preg_match('#/reinitialiser-mot-de-passe\?jeton=([a-f0-9]{64})#', (string) $mail->getTextBody(), $match));

        return $match[1];
    }

    /** Un jeton de session émis il y a longtemps, avant tout changement de mot de passe. */
    private function jetonAncien(string $email): string
    {
        $id = (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT id FROM app_user WHERE email = ?', [$email]);

        return JWT::encode(['sub' => $id, 'email' => $email, 'iat' => time() - 3600, 'exp' => time() + 3600], (string) $_SERVER['JWT_SECRET'], 'HS256');
    }
}
