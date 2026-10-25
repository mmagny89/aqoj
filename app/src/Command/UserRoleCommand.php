<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:role', description: 'Donne le rôle Membre ou VIP à un compte')]
final readonly class UserRoleCommand
{
    public function __construct(private UserRepository $users, private EntityManagerInterface $em)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Adresse e-mail du compte')]
        string $email,
        #[Argument(description: 'member ou vip')]
        string $role,
    ): int {
        $user = $this->users->findByEmail(strtolower(trim($email)));
        $parsed = UserRole::tryFrom($role);
        if ($user === null || $parsed === null) {
            $io->error($user === null ? 'Compte introuvable.' : 'Rôle inconnu : member ou vip.');

            return Command::FAILURE;
        }

        $user->setRole($parsed);
        $this->em->flush();
        $io->success(\sprintf('%s est maintenant %s.', $user->getEmail(), $parsed->label()));

        return Command::SUCCESS;
    }
}
