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

    /**
     * Un jeton émis avant le dernier changement de mot de passe ne vaut plus. Une seconde de tolérance :
     * la colonne est à la seconde (arrondie), et le jeton rendu juste après le changement ne doit jamais être refusé.
     */
    public function isRevoked(User $user, \stdClass $payload): bool
    {
        $changedAt = $user->getPasswordChangedAt();

        return $changedAt !== null && (int) ($payload->iat ?? 0) < $changedAt->getTimestamp() - 1;
    }

    public function decode(string $token): \stdClass
    {
        return JWT::decode($token, new Key($this->jwtSecret, self::ALGO));
    }
}
