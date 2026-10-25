<?php

namespace App\Controller;

use App\Entity\InvitationCode;
use App\Entity\User;
use App\Repository\InvitationCodeRepository;
use App\Repository\UserRepository;
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

        return $this->json([
            'token' => $jwt->generateToken($user),
            'user' => $user,
        ], 201);
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
            return $em->find(User::class, $payload->sub);
        } catch (\Throwable) {
            return null;
        }
    }
}
