<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Palier d'un compte. L'administration est un pouvoir à part (`User::isAdmin`), pas un palier.
 * Un VIP est aussi membre : `ROLE_MEMBER` est accordé aux deux.
 */
enum UserRole: string
{
    case Member = 'member';
    case Vip = 'vip';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'Membre',
            self::Vip => 'VIP',
        };
    }

    /** @return list<string> */
    public function securityRoles(): array
    {
        return match ($this) {
            self::Member => ['ROLE_MEMBER'],
            self::Vip => ['ROLE_MEMBER', 'ROLE_VIP'],
        };
    }
}
