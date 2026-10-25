<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/** E-mails du compte : lien de réinitialisation et avis de changement de mot de passe. */
final readonly class AccountMailer
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire(env: 'MAILER_FROM')]
        private string $from,
        /** Adresse publique du site, jamais déduite de l'en-tête Host d'une requête (lien empoisonné). */
        #[Autowire(env: 'PUBLIC_URL')]
        private string $publicUrl,
    ) {
    }

    public function sendResetLink(User $user, string $token): void
    {
        $link = rtrim($this->publicUrl, '/').'/reinitialiser-mot-de-passe?jeton='.$token;

        $this->send($user, 'Réinitialiser votre mot de passe', [
            'Vous avez demandé à réinitialiser votre mot de passe sur « À quoi on joue ».',
            "Ce lien est valable une heure et ne sert qu'une fois :",
            $link,
            "Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe reste inchangé.",
        ], $link, 'Choisir un nouveau mot de passe');
    }

    public function sendPasswordChanged(User $user): void
    {
        $this->send($user, 'Votre mot de passe a été modifié', [
            'Le mot de passe de votre compte « À quoi on joue » vient d\'être modifié.',
            "Si ce n'est pas vous, utilisez « Mot de passe oublié » sans attendre, puis prévenez l'association.",
        ]);
    }

    /**
     * @param list<string> $lines
     */
    private function send(User $user, string $subject, array $lines, ?string $link = null, ?string $linkLabel = null): void
    {
        $html = '<p>'.implode('</p><p>', array_map(static fn (string $line): string => htmlspecialchars($line, ENT_QUOTES), $lines)).'</p>';
        if ($link !== null) {
            $html = str_replace(htmlspecialchars($link, ENT_QUOTES), '<a href="'.htmlspecialchars($link, ENT_QUOTES).'">'.htmlspecialchars((string) $linkLabel, ENT_QUOTES).'</a>', $html);
        }

        $this->mailer->send(
            (new Email())
                ->from(new Address($this->from, 'À quoi on joue'))
                ->to($user->getEmail())
                ->subject($subject)
                ->text(implode("\n\n", $lines))
                ->html($html),
        );
    }
}
