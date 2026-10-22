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
