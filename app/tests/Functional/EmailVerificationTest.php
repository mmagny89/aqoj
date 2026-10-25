<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mime\Email;

final class EmailVerificationTest extends ApiTestCase
{
    private const PASSWORD = 'mot-de-passe-solide';

    public function testLInscriptionNeDonnePasDeSessionEtEnvoieLeLien(): void
    {
        $client = static::createClient();

        $reponse = $this->call($client, 'POST', '/api/auth/register', ['email' => 'nouveau@example.test', 'password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(201);
        self::assertTrue($reponse['verificationRequired']);
        self::assertArrayNotHasKey('token', $reponse);
        self::assertEmailCount(1);
        self::assertEmailAddressContains(self::getMailerMessage(0), 'To', 'nouveau@example.test');
        self::assertNull($this->confirmation('nouveau@example.test'));
    }

    public function testTantQueLAdresseNEstPasConfirmeeLaConnexionEstRefusee(): void
    {
        $client = static::createClient();
        $this->call($client, 'POST', '/api/auth/register', ['email' => 'attente@example.test', 'password' => self::PASSWORD]);

        $refus = $this->call($client, 'POST', '/api/auth/login', ['email' => 'attente@example.test', 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('email_not_verified', $refus['code']);

        // Un mauvais mot de passe ne dit rien de l'état du compte.
        $faux = $this->call($client, 'POST', '/api/auth/login', ['email' => 'attente@example.test', 'password' => 'faux-mot-de-passe']);
        self::assertResponseStatusCodeSame(401);
        self::assertArrayNotHasKey('code', $faux);
    }

    public function testLeLienConfirmeLAdresseOuvreLaSessionEtNeServQuUneFois(): void
    {
        $client = static::createClient();
        $this->call($client, 'POST', '/api/auth/register', ['email' => 'lien@example.test', 'password' => self::PASSWORD]);
        $jeton = $this->jetonDuMail();

        $ouvert = $this->call($client, 'POST', '/api/auth/verify-email', ['token' => $jeton]);
        self::assertResponseIsSuccessful();
        self::assertTrue($ouvert['user']['emailVerified']);
        $this->call($client, 'GET', '/api/auth/me', token: $ouvert['token']);
        self::assertResponseIsSuccessful();

        $this->call($client, 'POST', '/api/auth/verify-email', ['token' => $jeton]);
        self::assertResponseStatusCodeSame(422);
        $this->call($client, 'POST', '/api/auth/login', ['email' => 'lien@example.test', 'password' => self::PASSWORD]);
        self::assertResponseIsSuccessful();
    }

    public function testUnLienInconnuOuExpireEstRefuse(): void
    {
        $client = static::createClient();
        $this->call($client, 'POST', '/api/auth/register', ['email' => 'expire@example.test', 'password' => self::PASSWORD]);
        $jeton = $this->jetonDuMail();

        $this->call($client, 'POST', '/api/auth/verify-email', ['token' => 'nimportequoi']);
        self::assertResponseStatusCodeSame(422);

        static::getContainer()->get(Connection::class)->executeStatement("UPDATE user_token SET expires_at = NOW() - INTERVAL '1 minute'");
        $this->call($client, 'POST', '/api/auth/verify-email', ['token' => $jeton]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testUnJetonDeConfirmationNeReinitialiseJamaisUnMotDePasse(): void
    {
        $client = static::createClient();
        $this->call($client, 'POST', '/api/auth/register', ['email' => 'croise@example.test', 'password' => self::PASSWORD]);
        $jeton = $this->jetonDuMail();

        $this->call($client, 'POST', '/api/auth/reset-password', ['token' => $jeton, 'password' => 'pirate-mot-de-passe']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testLeRenvoiRepondLaMemeChoseEtNEnvoieQuAuxComptesNonConfirmes(): void
    {
        $client = static::createClient();
        $this->call($client, 'POST', '/api/auth/register', ['email' => 'renvoi@example.test', 'password' => self::PASSWORD]);
        $premier = $this->jetonDuMail();

        $attente = $this->call($client, 'POST', '/api/auth/resend-verification', ['email' => 'renvoi@example.test']);
        self::assertResponseStatusCodeSame(202);
        self::assertEmailCount(1);
        $second = $this->jetonDuMail();
        self::assertNotSame($premier, $second);

        $inconnu = $this->call($client, 'POST', '/api/auth/resend-verification', ['email' => 'personne@example.test']);
        self::assertEmailCount(0);
        self::assertSame($attente, $inconnu);

        // Le nouveau lien annule l'ancien.
        $this->call($client, 'POST', '/api/auth/verify-email', ['token' => $premier]);
        self::assertResponseStatusCodeSame(422);
        $this->call($client, 'POST', '/api/auth/verify-email', ['token' => $second]);
        self::assertResponseIsSuccessful();

        $this->call($client, 'POST', '/api/auth/resend-verification', ['email' => 'renvoi@example.test']);
        self::assertEmailCount(0);
    }

    public function testReinitialiserSonMotDePasseConfirmeAussiLAdresse(): void
    {
        $client = static::createClient();
        $this->call($client, 'POST', '/api/auth/register', ['email' => 'oubli@example.test', 'password' => self::PASSWORD]);
        $this->call($client, 'POST', '/api/auth/forgot-password', ['email' => 'oubli@example.test']);
        $jeton = $this->jetonDuMail();

        $this->call($client, 'POST', '/api/auth/reset-password', ['token' => $jeton, 'password' => 'nouveau-mot-de-passe']);
        self::assertResponseIsSuccessful();

        $this->call($client, 'POST', '/api/auth/login', ['email' => 'oubli@example.test', 'password' => 'nouveau-mot-de-passe']);
        self::assertResponseIsSuccessful();
    }

    public function testUnJetonDeSessionDUnCompteNonConfirmeNOuvreRien(): void
    {
        $client = static::createClient();
        $this->call($client, 'POST', '/api/auth/register', ['email' => 'fantome@example.test', 'password' => self::PASSWORD]);
        $jeton = $this->jetonDuMail();
        $session = $this->call($client, 'POST', '/api/auth/verify-email', ['token' => $jeton])['token'];
        static::getContainer()->get(Connection::class)->executeStatement("UPDATE app_user SET email_verified_at = NULL WHERE email = 'fantome@example.test'");

        $this->call($client, 'GET', '/api/auth/me', token: $session);

        self::assertResponseStatusCodeSame(401);
    }

    public function testLaPurgeSupprimeLesComptesJamaisConfirmesAnciens(): void
    {
        $client = static::createClient();
        $this->call($client, 'POST', '/api/auth/register', ['email' => 'ancien@example.test', 'password' => self::PASSWORD]);
        $this->call($client, 'POST', '/api/auth/register', ['email' => 'recent@example.test', 'password' => self::PASSWORD]);
        $connexion = static::getContainer()->get(Connection::class);
        $connexion->executeStatement("UPDATE app_user SET created_at = NOW() - INTERVAL '20 days' WHERE email = 'ancien@example.test'");

        $application = new Application(self::$kernel);
        $testeur = new CommandTester($application->find('app:maintenance:purge'));
        $testeur->execute([]);

        self::assertStringContainsString('1 compte(s) non confirmé(s)', $testeur->getDisplay());
        self::assertSame(
            ['recent@example.test'],
            $connexion->fetchFirstColumn("SELECT email FROM app_user WHERE email IN ('ancien@example.test', 'recent@example.test')"),
        );
    }

    private function confirmation(string $email): mixed
    {
        return static::getContainer()->get(Connection::class)->fetchOne('SELECT email_verified_at FROM app_user WHERE email = ?', [$email]) ?: null;
    }

    private function jetonDuMail(): string
    {
        $mail = self::getMailerMessage(0);
        self::assertInstanceOf(Email::class, $mail);
        self::assertSame(1, preg_match('#/(?:verifier-adresse|reinitialiser-mot-de-passe)\?jeton=([a-f0-9]{64})#', (string) $mail->getTextBody(), $match));

        return $match[1];
    }
}
