<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;

/**
 * Le compte connecté, tel que le pare-feu `api` l'a authentifié (JwtAuthenticator), ou null pour un visiteur.
 * Un seul chemin d'authentification : jeton expiré, révoqué ou d'un compte non confirmé = visiteur, partout.
 */
trait CurrentUser
{
    private function appUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }
}
