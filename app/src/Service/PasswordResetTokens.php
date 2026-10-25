<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

/**
 * Jetons de réinitialisation du mot de passe. Seule l'empreinte SHA-256 est stockée : une fuite de la
 * base ne donne aucun lien utilisable. Un jeton sert une fois, pendant une heure ; en demander un nouveau
 * annule les précédents.
 */
final readonly class PasswordResetTokens
{
    public const TTL = '+1 hour';

    public function __construct(private Connection $connection)
    {
    }

    /** @return string le jeton en clair, à envoyer par e-mail et jamais conservé */
    public function issue(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        $now = new \DateTimeImmutable();

        $this->connection->transactional(function (Connection $tx) use ($user, $token, $now): void {
            $tx->executeStatement('UPDATE password_reset_request SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [$now, $user->getId()], [Types::DATETIME_IMMUTABLE, Types::INTEGER]);
            $tx->executeStatement(
                'INSERT INTO password_reset_request (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)',
                [$user->getId(), self::hash($token), $now->modify(self::TTL), $now],
                [Types::INTEGER, Types::STRING, Types::DATETIME_IMMUTABLE, Types::DATETIME_IMMUTABLE],
            );
        });

        return $token;
    }

    /**
     * Consomme le jeton en un seul UPDATE : deux demandes simultanées avec le même lien ne passent pas toutes les deux.
     *
     * @return int|null l'identifiant du compte, ou null si le jeton est inconnu, expiré ou déjà utilisé
     */
    public function consume(string $token): ?int
    {
        $id = $this->connection->fetchOne(
            'UPDATE password_reset_request SET used_at = NOW() WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() RETURNING user_id',
            [self::hash($token)],
        );

        return $id === false ? null : (int) $id;
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
