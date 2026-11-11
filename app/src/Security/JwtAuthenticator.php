<?php

namespace App\Security;

use App\Repository\UserRepository;
use App\Service\JwtService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Authentifie une requête par son jeton Bearer. Un jeton absent, expiré ou
 * illisible laisse la requête anonyme : les routes publiques continuent de
 * répondre, et c'est `access_control` qui refuse les routes protégées.
 */
final class JwtAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly JwtService $jwt,
        private readonly UserRepository $users,
    ) {}

    public function supports(Request $request): bool
    {
        $id = $this->userId($request);
        if ($id === null) {
            return false;
        }

        // Un jeton révoqué (mot de passe changé depuis) ou d'un compte non confirmé se comporte comme un jeton expiré : la requête reste anonyme.
        $user = $this->users->find($id);
        $payload = $this->payload($request);

        return !($user !== null && $payload !== null && !$this->jwt->accepts($user, $payload));
    }

    public function authenticate(Request $request): Passport
    {
        $id = $this->userId($request) ?? throw new AuthenticationException('Jeton invalide.');

        return new SelfValidatingPassport(new UserBadge((string) $id, fn() => $this->users->find($id)));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(['error' => 'Non authentifié.'], 401);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(['error' => 'Authentification requise.'], 401);
    }

    private function payload(Request $request): ?\stdClass
    {
        return $this->jwt->payloadFromRequest($request);
    }

    private function userId(Request $request): ?int
    {
        $sub = $this->payload($request)->sub ?? null;

        return is_int($sub) || ctype_digit((string) $sub) ? (int) $sub : null;
    }
}
