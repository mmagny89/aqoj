<?php

namespace App\Translation;

use App\Entity\Game;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Traduit la description (anglaise) d'un jeu et garde le résultat en base : un jeu n'est traduit qu'une fois,
 * sauf si BGG change sa description. BGG n'a qu'une description par jeu, en anglais.
 */
class DescriptionTranslator
{
    /** Délai avant de retenter un jeu dont la traduction a échoué. */
    private const RETRY_AFTER = '-1 day';

    private const FRENCH_WORDS = ['le', 'la', 'les', 'des', 'une', 'est', 'dans', 'pour', 'avec', 'sont', 'vous', 'joueurs', 'jeu', 'qui', 'sur', 'du', 'au', 'aux', 'et'];
    private const ENGLISH_WORDS = ['the', 'and', 'of', 'is', 'in', 'to', 'players', 'game', 'with', 'for', 'are', 'you', 'that', 'on', 'this', 'a', 'an', 'their', 'each', 'by'];

    public function __construct(
        private readonly LibreTranslateClient $client,
        private readonly EntityManagerInterface $em,
    ) {}

    /** La description attend-elle une traduction (jamais traduite, ou changée depuis) ? */
    public function needsTranslation(Game $game): bool
    {
        return $game->getDescription() !== null && $game->getDescription() !== '' && !$game->hasFreshTranslation();
    }

    /** Peut-on retenter maintenant ? Un essai par jour et par jeu au plus, réussi ou non. */
    public function mayAttempt(Game $game): bool
    {
        $last = $game->getDescriptionFrAttemptAt();

        return $last === null || $last <= new \DateTimeImmutable(self::RETRY_AFTER);
    }

    /**
     * Traduit la description si elle en a besoin. Rend true quand le jeu a une traduction à jour ensuite.
     * Ne fait rien (et rend false) si le service ne répond pas : la fiche reste en anglais.
     */
    public function translate(Game $game, float $timeout = 30.0): bool
    {
        if (!$this->needsTranslation($game)) {
            return $game->hasFreshTranslation();
        }

        $source = (string) $game->getDescription();
        $now = new \DateTimeImmutable();
        $game->setDescriptionFrAttemptAt($now);

        // Une description déjà française (rare : l'éditeur l'a saisie ainsi) se garde telle quelle.
        if ($this->looksFrench($source)) {
            $game->setTranslation($source, md5($source), $now);
            $this->em->flush();

            return true;
        }

        // On traduit paragraphe par paragraphe et on garde les sauts de ligne d'origine.
        $pieces = preg_split('/(\R+)/u', $source, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$source];
        $positions = [];
        $paragraphs = [];
        foreach ($pieces as $i => $piece) {
            if (trim($piece) !== '' && !preg_match('/^\R+$/u', $piece)) {
                $positions[] = $i;
                $paragraphs[] = $piece;
            }
        }

        $translated = $this->client->translate($paragraphs, $timeout);
        if ($translated === null) {
            $this->em->flush();

            return false;
        }

        foreach ($positions as $n => $i) {
            $pieces[$i] = $translated[$n];
        }
        $game->setTranslation(trim(implode('', $pieces)), md5($source), $now);
        $this->em->flush();

        return true;
    }

    /** Plus de mots courants français qu'anglais, sur le début du texte. */
    public function looksFrench(string $text): bool
    {
        $words = preg_split('/[^\p{L}]+/u', mb_strtolower(mb_substr($text, 0, 600)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $french = count(array_filter($words, fn(string $w) => in_array($w, self::FRENCH_WORDS, true)));
        $english = count(array_filter($words, fn(string $w) => in_array($w, self::ENGLISH_WORDS, true)));

        return $french > $english * 2 && $french >= 4;
    }
}
