<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Service\JwtService;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use PHPUnit\Framework\TestCase;

final class JwtServiceTest extends TestCase
{
    private const SECRET = 'secret-de-test-0123456789abcdef0123456789abcdef';

    public function testLeTokenGenereSeDecodeVersLUtilisateur(): void
    {
        $service = new JwtService(self::SECRET);
        $user = (new User())->setEmail('a@example.test');

        $payload = $service->decode($service->generateToken($user));

        self::assertSame('a@example.test', $payload->email);
        self::assertGreaterThan(time(), $payload->exp);
    }

    public function testUneSessionVitHuitHeuresEtGardeLHeureDeConnexion(): void
    {
        $service = new JwtService(self::SECRET);

        $payload = $service->decode($service->generateToken((new User())->setEmail('a@example.test')));

        self::assertSame(28800, $payload->exp - $payload->iat);
        self::assertSame($payload->iat, $payload->auth_time);
    }

    public function testLeRenouvellementPrologeSansChangerLaConnexionDOrigine(): void
    {
        $service = new JwtService(self::SECRET);
        $user = (new User())->setEmail('a@example.test');
        $origine = time() - 86400 * 3;
        $ancien = $service->decode($service->generateToken($user, $origine));

        $nouveau = $service->decode((string) $service->renew($user, $ancien));

        self::assertSame($origine, $nouveau->auth_time);
        self::assertGreaterThanOrEqual($ancien->exp, $nouveau->exp);
    }

    public function testLeRenouvellementSArreteAuPlafondDeTrenteJours(): void
    {
        $service = new JwtService(self::SECRET);
        $user = (new User())->setEmail('a@example.test');

        $trop_vieux = $service->decode($service->generateToken($user, time() - 86400 * 31));
        $limite = $service->decode($service->generateToken($user, time() - 86400 * 29));

        self::assertNull($service->renew($user, $trop_vieux));
        self::assertNotNull($service->renew($user, $limite));
    }

    public function testUnAncienJetonSansHeureDeConnexionCompteDepuisSonEmission(): void
    {
        $service = new JwtService(self::SECRET);
        $user = (new User())->setEmail('a@example.test');

        self::assertNull($service->renew($user, (object) ['iat' => time() - 86400 * 40]));
        self::assertNotNull($service->renew($user, (object) ['iat' => time() - 3600]));
    }

    public function testUnTokenSigneAvecUnAutreSecretEstRefuse(): void
    {
        $token = (new JwtService(self::SECRET))->generateToken((new User())->setEmail('a@example.test'));

        $this->expectException(SignatureInvalidException::class);
        (new JwtService('un-autre-secret-0123456789abcdef0123456789'))->decode($token);
    }

    public function testUnTokenExpireEstRefuse(): void
    {
        $token = \Firebase\JWT\JWT::encode(['sub' => 1, 'exp' => time() - 60], self::SECRET, 'HS256');

        $this->expectException(ExpiredException::class);
        (new JwtService(self::SECRET))->decode($token);
    }
}
