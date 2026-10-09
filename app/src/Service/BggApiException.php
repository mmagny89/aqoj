<?php

namespace App\Service;

/**
 * Échec d'un appel BGG. Le code porte le statut HTTP (0 = BGG injoignable).
 */
final class BggApiException extends \RuntimeException
{
}
