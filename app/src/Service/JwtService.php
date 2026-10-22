<?php

namespace App\Service;

use App\Entity\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private const TTL = 86400; // 24h
    private const ALGO = 'HS256';

    public function __construct(private readonly string $jwtSecret) {}

    public function generateToken(User $user): string
    {
        $now = time();
        $payload = [
            'sub' => $user->getId(),
            'email' => $user->getEmail(),
            'iat' => $now,
            'exp' => $now + self::TTL,
        ];

        return JWT::encode($payload, $this->jwtSecret, self::ALGO);
    }

    public function decode(string $token): \stdClass
    {
        return JWT::decode($token, new Key($this->jwtSecret, self::ALGO));
    }
}
