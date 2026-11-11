<?php

declare(strict_types=1);

namespace App\Catalogue;

final readonly class ImportReport
{
    public function __construct(
        /** Lignes exploitables lues dans la source (extensions comprises). */
        public int $read,
        public int $created,
        public int $updated,
        /** Extensions laissées de côté faute de `--include-expansions`. */
        public int $skippedExpansions,
    ) {
    }
}
