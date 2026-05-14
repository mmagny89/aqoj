<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\JwtService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    #[Route('/api/auth/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        JwtService $jwt,
    ): JsonResponse {
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

        $user = (new User())
            ->setEmail($email)
            ->setPasswordHash(password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]));

        $em->persist($user);
        $em->flush();

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
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = trim(strtolower($data['email'] ?? ''));
        $password = $data['password'] ?? '';

        $user = $userRepository->findByEmail($email);

        if (!$user || !password_verify($password, $user->getPasswordHash())) {
            return $this->json(['error' => 'E-mail ou mot de passe incorrect.'], 401);
        }

        return $this->json([
            'token' => $jwt->generateToken($user),
            'user' => $user,
        ]);
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
