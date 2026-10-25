<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

/**
 * Jetons à usage unique envoyés par e-mail : mot de passe oublié (une heure) et confirmation d'adresse
 * (sept jours). Seule l'empreinte SHA-256 est stockée : une fuite de la base ne donne aucun lien utilisable.
 * En demander un nouveau, pour la même finalité, annule les précédents.
 */
final readonly class UserTokens
{
    public const PASSWORD_RESET = 'password_reset';
    public const EMAIL_VERIFICATION = 'email_verification';

    private const TTL = [
        self::PASSWORD_RESET => '+1 hour',
        self::EMAIL_VERIFICATION => '+7 days',
    ];

    public function __construct(private Connection $connection)
    {
    }

    /** @return string le jeton en clair, à envoyer par e-mail et jamais conservé */
    public function issue(User $user, string $purpose): string
    {
        $ttl = self::TTL[$purpose] ?? throw new \InvalidArgumentException("Finalité inconnue : $purpose.");
        $token = bin2hex(random_bytes(32));
        $now = new \DateTimeImmutable();

        $this->connection->transactional(function (Connection $tx) use ($user, $purpose, $token, $now, $ttl): void {
            $tx->executeStatement(
                'UPDATE user_token SET used_at = ? WHERE user_id = ? AND purpose = ? AND used_at IS NULL',
                [$now, $user->getId(), $purpose],
                [Types::DATETIME_IMMUTABLE, Types::INTEGER, Types::STRING],
            );
            $tx->executeStatement(
                'INSERT INTO user_token (user_id, purpose, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?)',
                [$user->getId(), $purpose, self::hash($token), $now->modify($ttl), $now],
                [Types::INTEGER, Types::STRING, Types::STRING, Types::DATETIME_IMMUTABLE, Types::DATETIME_IMMUTABLE],
            );
        });

        return $token;
    }

    /**
     * Consomme le jeton en un seul UPDATE : deux demandes simultanées avec le même lien ne passent pas toutes les deux.
     * Un jeton d'une autre finalité ne sert pas ici : un lien de confirmation ne réinitialise pas un mot de passe.
     *
     * @return int|null l'identifiant du compte, ou null si le jeton est inconnu, expiré, déjà utilisé ou d'une autre finalité
     */
    public function consume(string $token, string $purpose): ?int
    {
        $id = $this->connection->fetchOne(
            'UPDATE user_token SET used_at = NOW() WHERE token_hash = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW() RETURNING user_id',
            [self::hash($token), $purpose],
        );

        return $id === false ? null : (int) $id;
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
