<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:admin',
    description: "Donne ou retire le statut d'administrateur",
    help: "Le statut d'administrateur ne se donne pas depuis l'interface : seule cette commande le fait.",
)]
final readonly class UserAdminCommand
{
    public function __construct(private UserRepository $users, private EntityManagerInterface $em)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Adresse e-mail du compte')]
        string $email,
        #[Option(description: 'Retirer le statut au lieu de le donner')]
        bool $revoke = false,
    ): int {
        $user = $this->users->findByEmail(strtolower(trim($email)));
        if ($user === null) {
            $io->error('Compte introuvable.');

            return Command::FAILURE;
        }

        $user->setIsAdmin(!$revoke);
        $this->em->flush();
        $io->success(\sprintf('%s %s.', $user->getEmail(), $revoke ? "n'est plus administrateur" : 'est administrateur'));

        return Command::SUCCESS;
    }
}
