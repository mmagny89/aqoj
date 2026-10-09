<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Verrou des tâches qui appellent BGG : deux passages simultanés doubleraient
 * la cadence imposée par l'API. Verrou consultatif PostgreSQL, lié à la
 * connexion : il tombe tout seul si le processus meurt, et fonctionne entre
 * conteneurs sans volume partagé.
 */
class BggJobLock
{
    private const NAME = 'bgg-api';

    private bool $held = false;

    public function __construct(private readonly Connection $connection) {}

    public function acquire(): bool
    {
        if ($this->held) {
            return true;
        }

        $this->held = (bool) $this->connection->fetchOne('SELECT pg_try_advisory_lock(hashtext(?))', [self::NAME]);

        return $this->held;
    }

    public function release(): void
    {
        if ($this->held) {
            $this->connection->fetchOne('SELECT pg_advisory_unlock(hashtext(?))', [self::NAME]);
            $this->held = false;
        }
    }
}
