<?php

namespace App\Controller;

use App\Entity\InvitationCode;
use App\Entity\User;
use App\Repository\InvitationCodeRepository;
use App\Repository\UserRepository;
use App\Service\AccountMailer;
use App\Service\UserTokens;
use App\Service\JwtService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    private const INVALID_CODE = "Code d'invitation invalide ou expiré.";

    /** Dit au formulaire d'inscription s'il doit réclamer un code d'invitation. */
    #[Route('/api/auth/registration', name: 'api_auth_registration', methods: ['GET'])]
    public function registration(#[Autowire(env: 'bool:REGISTRATION_REQUIRES_CODE')] bool $requiresCode): JsonResponse
    {
        return $this->json(['requiresCode' => $requiresCode]);
    }

    #[Route('/api/auth/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        JwtService $jwt,
        RateLimiterFactoryInterface $registerLimiter,
        InvitationCodeRepository $invitations,
        UserTokens $tokens,
        AccountMailer $mailer,
        #[Autowire(env: 'bool:REGISTRATION_REQUIRES_CODE')]
        bool $requiresCode,
    ): JsonResponse {
        $limit = $registerLimiter->create($request->getClientIp() ?? 'inconnue')->consume();
        if (!$limit->isAccepted()) {
            return $this->tooManyAttempts($limit);
        }

        $data = json_decode($request->getContent(), true);
        $email = trim(strtolower($data['email'] ?? ''));
        $password = $data['password'] ?? '';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Adresse e-mail invalide.'], 422);
        }

        if (strlen($password) < 8) {
            return $this->json(['error' => 'Le mot de passe doit contenir au moins 8 caractères.'], 422);
        }

        if ($userRepository->findByEmail($email)) {
            return $this->json(['error' => 'Un compte existe déjà avec cet e-mail.'], 409);
        }

        // Un code est exigé selon l'environnement, et vérifié dès qu'il est fourni. L'erreur est la même
        // dans tous les cas : elle ne dit pas si le code existe, ni s'il était obligatoire.
        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '' && $requiresCode) {
            return $this->json(['error' => self::INVALID_CODE], 422);
        }

        $user = (new User())
            ->setEmail($email)
            ->setPasswordHash(password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]));

        // Usage consommé et compte créé dans la même transaction : un échec n'use pas le code.
        $accepted = $em->wrapInTransaction(function () use ($code, $invitations, $user, $em): bool {
            if ($code !== '') {
                $used = $invitations->consume($code);
                if ($used === null) {
                    return false;
                }
                $user->setRole($used['role'])->setInvitationCode($em->getReference(InvitationCode::class, $used['id']));
            }
            $em->persist($user);
            $em->flush();

            return true;
        });
        if (!$accepted) {
            return $this->json(['error' => self::INVALID_CODE], 422);
        }

        // Pas de session avant d'avoir prouvé que l'adresse est la sienne : le lien arrive par e-mail.
        $this->sendVerification($user, $tokens, $mailer);

        return $this->json([
            'verificationRequired' => true,
            'message' => 'Compte créé. Un e-mail de confirmation vient de vous être envoyé.',
        ], 201);
    }

    /** Confirme l'adresse avec le lien reçu, et ouvre la session : c'est la preuve qu'on cherchait. */
    #[Route('/api/auth/verify-email', name: 'api_auth_verify_email', methods: ['POST'])]
    public function verifyEmail(
        Request $request,
        UserRepository $userRepository,
        UserTokens $tokens,
        EntityManagerInterface $em,
        JwtService $jwt,
        RateLimiterFactoryInterface $passwordResetLimiter,
    ): JsonResponse {
        $limit = $passwordResetLimiter->create('verify-'.($request->getClientIp() ?? 'inconnue'))->consume();
        if (!$limit->isAccepted()) {
            return $this->tooManyAttempts($limit);
        }

        $data = json_decode($request->getContent(), true);
        $token = trim((string) (is_array($data) ? ($data['token'] ?? '') : ''));

        $userId = $token !== '' ? $tokens->consume($token, UserTokens::EMAIL_VERIFICATION) : null;
        $user = $userId !== null ? $userRepository->find($userId) : null;
        if ($user === null) {
            return $this->json(['error' => 'Lien invalide ou expiré. Demandez-en un nouveau.'], 422);
        }

        $user->markEmailVerified();
        $em->flush();

        return $this->json(['token' => $jwt->generateToken($user), 'user' => $user]);
    }

    /** Renvoie le lien de confirmation. Réponse identique que le compte existe, soit confirmé ou non. */
    #[Route('/api/auth/resend-verification', name: 'api_auth_resend_verification', methods: ['POST'])]
    public function resendVerification(
        Request $request,
        UserRepository $userRepository,
        UserTokens $tokens,
        AccountMailer $mailer,
        RateLimiterFactoryInterface $passwordResetLimiter,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = trim(strtolower((string) (is_array($data) ? ($data['email'] ?? '') : '')));

        $byAddress = $passwordResetLimiter->create('ip-'.($request->getClientIp() ?? 'inconnue'))->consume();
        $byEmail = $passwordResetLimiter->create('verification-'.sha1($email))->consume();
        if (!$byAddress->isAccepted() || !$byEmail->isAccepted()) {
            return $this->tooManyAttempts($byAddress->isAccepted() ? $byEmail : $byAddress);
        }

        $user = $email !== '' ? $userRepository->findByEmail($email) : null;
        if ($user !== null && !$user->isEmailVerified()) {
            $this->sendVerification($user, $tokens, $mailer);
        }

        return $this->json(['message' => "Si un compte à confirmer existe avec cette adresse, un e-mail vient de lui être envoyé."], 202);
    }

    private function sendVerification(User $user, UserTokens $tokens, AccountMailer $mailer): void
    {
        try {
            $mailer->sendVerificationLink($user, $tokens->issue($user, UserTokens::EMAIL_VERIFICATION));
        } catch (\Throwable) {
            // Un transport en panne ne doit pas faire échouer l'inscription : « renvoyer l'e-mail » reste possible.
        }
    }

    #[Route('/api/auth/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        JwtService $jwt,
        RateLimiterFactoryInterface $loginLimiter,
        RateLimiterFactoryInterface $loginIpLimiter,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = trim(strtolower($data['email'] ?? ''));
        $password = $data['password'] ?? '';

        // Chaque essai compte, réussi ou non : un limiteur qui ne compterait que les échecs
        // se laisserait contourner en alternant avec un compte valide.
        $ip = $request->getClientIp() ?? 'inconnue';
        $limit = $loginIpLimiter->create($ip)->consume();
        $limitPair = $loginLimiter->create($ip . '|' . $email)->consume();
        if (!$limit->isAccepted() || !$limitPair->isAccepted()) {
            return $this->tooManyAttempts($limitPair->isAccepted() ? $limit : $limitPair);
        }

        $user = $userRepository->findByEmail($email);

        // password_verify même si le compte n'existe pas : le temps de réponse ne révèle pas l'e-mail.
        $hash = $user?->getPasswordHash() ?? '$2y$12$0123456789012345678901uJ0Tn1Q2yQ7Z3b2m0m5c0m0m0m0m0m0';
        if (!password_verify($password, $hash) || !$user) {
            return $this->json(['error' => 'E-mail ou mot de passe incorrect.'], 401);
        }

        // Le mot de passe est bon : on peut dire que l'adresse reste à confirmer sans rien révéler à un tiers.
        if (!$user->isEmailVerified()) {
            return $this->json([
                'error' => 'Adresse e-mail non confirmée. Ouvrez le lien reçu par e-mail, ou demandez-en un nouveau.',
                'code' => 'email_not_verified',
            ], 403);
        }

        return $this->json([
            'token' => $jwt->generateToken($user),
            'user' => $user,
        ]);
    }

    private function tooManyAttempts(RateLimit $limit): JsonResponse
    {
        $wait = max(1, $limit->getRetryAfter()->getTimestamp() - time());

        return $this->json(
            ['error' => 'Trop de tentatives. Réessayez dans quelques minutes.'],
            429,
            ['Retry-After' => (string) $wait],
        );
    }

    /**
     * Mot de passe oublié. La réponse est la même que le compte existe ou non : elle ne révèle pas
     * quelles adresses sont inscrites. Le lien arrive par e-mail, jamais dans la réponse.
     */
    #[Route('/api/auth/forgot-password', name: 'api_auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        UserTokens $tokens,
        AccountMailer $mailer,
        RateLimiterFactoryInterface $passwordResetLimiter,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = trim(strtolower((string) (is_array($data) ? ($data['email'] ?? '') : '')));

        $byAddress = $passwordResetLimiter->create('ip-'.($request->getClientIp() ?? 'inconnue'))->consume();
        $byEmail = $passwordResetLimiter->create('mail-'.sha1($email))->consume();
        if (!$byAddress->isAccepted() || !$byEmail->isAccepted()) {
            return $this->tooManyAttempts($byAddress->isAccepted() ? $byEmail : $byAddress);
        }

        $user = $email !== '' ? $userRepository->findByEmail($email) : null;
        if ($user !== null) {
            try {
                $mailer->sendResetLink($user, $tokens->issue($user, UserTokens::PASSWORD_RESET));
            } catch (\Throwable) {
                // Un transport en panne ne doit pas se voir de l'extérieur : la réponse reste identique.
            }
        }

        return $this->json(['message' => 'Si un compte existe avec cette adresse, un e-mail vient de lui être envoyé.'], 202);
    }

    #[Route('/api/auth/reset-password', name: 'api_auth_reset_password', methods: ['POST'])]
    public function resetPassword(
        Request $request,
        UserRepository $userRepository,
        UserTokens $tokens,
        AccountMailer $mailer,
        EntityManagerInterface $em,
        RateLimiterFactoryInterface $passwordResetLimiter,
    ): JsonResponse {
        $limit = $passwordResetLimiter->create('reset-'.($request->getClientIp() ?? 'inconnue'))->consume();
        if (!$limit->isAccepted()) {
            return $this->tooManyAttempts($limit);
        }

        $data = json_decode($request->getContent(), true);
        $data = is_array($data) ? $data : [];
        $token = trim((string) ($data['token'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if (strlen($password) < 8) {
            return $this->json(['error' => 'Le mot de passe doit contenir au moins 8 caractères.'], 422);
        }

        $userId = $token !== '' ? $tokens->consume($token, UserTokens::PASSWORD_RESET) : null;
        $user = $userId !== null ? $userRepository->find($userId) : null;
        if ($user === null) {
            return $this->json(['error' => 'Lien invalide ou expiré. Demandez-en un nouveau.'], 422);
        }

        // Recevoir ce lien prouve que l'adresse est la sienne : la confirmation vient avec.
        $user->changePasswordHash(password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]))->markEmailVerified();
        $em->flush();
        try {
            $mailer->sendPasswordChanged($user);
        } catch (\Throwable) {
        }

        return $this->json(['message' => 'Mot de passe modifié. Vous pouvez vous connecter.']);
    }

    #[Route('/api/auth/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(Request $request, JwtService $jwt, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->extractUser($request, $jwt, $em);
        if (!$user) {
            return $this->json(['error' => 'Non authentifié.'], 401);
        }

        return $this->json($user);
    }

    public static function extractUser(Request $request, JwtService $jwt, EntityManagerInterface $em): ?User
    {
        $header = $request->headers->get('Authorization', '');
        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }

        try {
            $payload = $jwt->decode(substr($header, 7));
            $user = $em->find(User::class, $payload->sub);

            return $user !== null && !$jwt->accepts($user, $payload) ? null : $user;
        } catch (\Throwable) {
            return null;
        }
    }
}
