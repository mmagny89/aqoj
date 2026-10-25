<?php

declare(strict_types=1);

namespace App\Catalogue\Import;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Une source d'import. Chaque implémentation déclare son identifiant — celui
 * passé à `app:import:catalogue --source` — par `#[AsTaggedItem(index: …)]`.
 */
#[AutoconfigureTag]
interface SourceImporterInterface
{
    /**
     * Lit la source sans rien écrire : le rapprochement avec le catalogue est
     * l'affaire de l'appelant. Les lignes inexploitables sont ignorées.
     *
     * @return iterable<GameData>
     *
     * @throws InvalidSourceException si la source est illisible ou mal structurée
     */
    public function import(\SplFileInfo $file): iterable;
}
