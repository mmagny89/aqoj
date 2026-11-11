<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InvitationCode;
use App\Enum\UserRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InvitationCode> */
class InvitationCodeRepository extends ServiceEntityRepository
{
    /** Sans I, O, 0 ni 1 : un code se lit à voix haute et se recopie à la main. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const LENGTH = 10;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvitationCode::class);
    }

    /** Forme stockée : majuscules, sans espace ni tiret. */
    public static function normalize(string $code): string
    {
        return strtoupper((string) preg_replace('/[\s\-]+/', '', $code));
    }

    /** XXXXX-XXXXX, pour l'affichage et la copie. */
    public static function format(string $code): string
    {
        return implode('-', str_split($code, (int) (self::LENGTH / 2)));
    }

    public function generateCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < self::LENGTH; ++$i) {
                $code .= self::ALPHABET[random_int(0, \strlen(self::ALPHABET) - 1)];
            }
        } while ($this->findOneBy(['code' => $code]) !== null);

        return $code;
    }

    /**
     * Consomme un usage du code, ou rien : un seul UPDATE, donc deux inscriptions simultanées sur le
     * dernier usage ne passent pas toutes les deux. À appeler dans la transaction qui crée le compte.
     *
     * @return array{id: int, role: UserRole}|null null si le code est inconnu, expiré, révoqué ou épuisé
     */
    public function consume(string $code): ?array
    {
        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            'UPDATE invitation_code SET used_count = used_count + 1
             WHERE code = :code AND revoked_at IS NULL AND used_count < max_uses
               AND (expires_at IS NULL OR expires_at > NOW())
             RETURNING id, role',
            ['code' => self::normalize($code)],
        );

        return $row === false ? null : ['id' => (int) $row['id'], 'role' => UserRole::from($row['role'])];
    }
}
