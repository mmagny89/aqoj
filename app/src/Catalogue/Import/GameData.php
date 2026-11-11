<?php

declare(strict_types=1);

namespace App\Catalogue\Import;

/**
 * Jeu lu dans une source, avant rapprochement avec le catalogue.
 */
final readonly class GameData
{
    public function __construct(
        public string $bggId,
        public string $name,
        public ?int $yearPublished,
        public ?int $bggRank,
        public ?float $ratingBgg,
        public ?int $usersRated,
        public bool $isExpansion,
    ) {
    }
}
