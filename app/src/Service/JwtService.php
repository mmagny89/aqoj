<?php

namespace App\Service;

use App\Entity\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\HttpFoundation\Request;

class JwtService
{
    /** Un jeton vit 8 h : le volé ne sert pas longtemps, et le front le renouvelle tant que la personne est là. */
    public const TTL = 28800;
    /** Plafond absolu : le renouvellement est glissant, mais une session ne dépasse jamais 30 jours depuis la dernière connexion. */
    public const MAX_SESSION = 2592000;
    private const ALGO = 'HS256';

    public function __construct(private readonly string $jwtSecret) {}

    /**
     * @param int|null $authTime instant de la connexion d'origine, conservé à chaque renouvellement (défaut : maintenant)
     */
    public function generateToken(User $user, ?int $authTime = null): string
    {
        $now = time();
        $payload = [
            'sub' => $user->getId(),
            'email' => $user->getEmail(),
            'iat' => $now,
            'auth_time' => $authTime ?? $now,
            'exp' => $now + self::TTL,
        ];

        return JWT::encode($payload, $this->jwtSecret, self::ALGO);
    }

    /**
     * Renouvelle une session encore valide, sans redemander le mot de passe, tant que le plafond de 30 jours
     * depuis la connexion d'origine n'est pas atteint. Un jeton d'avant `auth_time` compte depuis son émission.
     *
     * @return string|null le nouveau jeton, ou null si la session est trop ancienne
     */
    public function renew(User $user, \stdClass $payload): ?string
    {
        $authTime = (int) ($payload->auth_time ?? $payload->iat ?? 0);
        if (time() - $authTime > self::MAX_SESSION) {
            return null;
        }

        return $this->generateToken($user, $authTime);
    }

    /** Le contenu du jeton Bearer de la requête, ou null s'il manque, est expiré, mal signé ou illisible. */
    public function payloadFromRequest(Request $request): ?\stdClass
    {
        $header = $request->headers->get('Authorization', '');
        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }

        try {
            return $this->decode(substr($header, 7));
        } catch (\Throwable) {
            return null;
        }
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

    /**
     * Le jeton ouvre-t-il une session ? Non si le mot de passe a changé depuis son émission, ni si l'adresse du
     * compte n'est pas confirmée : dans les deux cas la requête reste anonyme, comme avec un jeton expiré.
     */
    public function accepts(User $user, \stdClass $payload): bool
    {
        return $user->isEmailVerified() && !$this->isRevoked($user, $payload);
    }

    public function decode(string $token): \stdClass
    {
        return JWT::decode($token, new Key($this->jwtSecret, self::ALGO));
    }
}
