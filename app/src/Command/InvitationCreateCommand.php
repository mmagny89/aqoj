<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\InvitationCode;
use App\Enum\UserRole;
use App\Repository\InvitationCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:invitation:create', description: "Crée un code d'invitation pour l'inscription")]
final readonly class InvitationCreateCommand
{
    public function __construct(private InvitationCodeRepository $invitations, private EntityManagerInterface $em)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Libellé : « Soirée du 12 octobre », « Pour Camille »')]
        string $label,
        #[Option(description: 'Rôle accordé : member ou vip')]
        string $role = 'member',
        #[Option(description: "Nombre d'usages")]
        int $uses = 1,
        #[Option(description: 'Date limite AAAA-MM-JJ (fin de journée)')]
        ?string $until = null,
    ): int {
        $parsed = UserRole::tryFrom($role);
        if ($parsed === null || $uses < 1 || trim($label) === '') {
            $io->error('Libellé obligatoire, rôle member ou vip, au moins un usage.');

            return Command::FAILURE;
        }

        $expiresAt = null;
        if ($until !== null) {
            $expiresAt = \DateTimeImmutable::createFromFormat('!Y-m-d', $until)?->modify('+1 day');
            if ($expiresAt === null) {
                $io->error('Date limite : AAAA-MM-JJ.');

                return Command::FAILURE;
            }
        }

        $code = new InvitationCode($this->invitations->generateCode(), trim($label), $parsed, $uses, $expiresAt);
        $this->em->persist($code);
        $this->em->flush();

        $io->success(\sprintf('Code %s (%s, %d usage%s).', InvitationCodeRepository::format($code->getCode()), $parsed->label(), $uses, $uses > 1 ? 's' : ''));

        return Command::SUCCESS;
    }
}
