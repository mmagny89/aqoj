<?php

namespace App\Service;

use App\Entity\Game;
use App\Repository\GameRepository;

class RecommendationService
{
    public function __construct(private readonly GameRepository $gameRepository) {}

    public function recommend(array $criteria): array
    {
        $players = isset($criteria['players']) && $criteria['players'] > 0 ? (int) $criteria['players'] : null;
        $maxTime = isset($criteria['maxTime']) && $criteria['maxTime'] > 0 ? (int) $criteria['maxTime'] : null;
        $categories = (array) ($criteria['categories'] ?? []);
        $mechanics = (array) ($criteria['mechanics'] ?? []);

        $games = $this->gameRepository->findByCriteria($players, $maxTime, $categories, $mechanics);

        if (empty($games)) {
            return [];
        }

        $scored = array_map(fn(Game $game) => [
            'game' => $game,
            'score' => $this->computeScore($game, $players, $maxTime, $categories, $mechanics),
            'reason' => $this->buildReason($game, $players, $maxTime, $categories),
        ], $games);

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, 10);
    }

    public function findForgottenGems(): array
    {
        $games = $this->gameRepository->findNotPlayedRecently(180, 10);

        return array_map(fn(Game $game) => [
            'game' => $game,
            'reason' => $this->buildForgottenReason($game),
        ], $games);
    }

    private function computeScore(Game $game, ?int $players, ?int $maxTime, array $categories, array $mechanics): float
    {
        $score = 0.0;

        $score += ($game->getRatingBgg() ?? 0) * 3;

        if ($players !== null) {
            if ($players >= $game->getMinPlayers() && $players <= $game->getMaxPlayers()) {
                $score += 20;
            } else {
                $score -= 40;
            }
        }

        if ($maxTime !== null && $game->getPlayingTime() > 0) {
            if ($game->getPlayingTime() <= $maxTime) {
                $score += 15;
                if ($game->getPlayingTime() <= $maxTime * 0.6) {
                    $score += 5;
                }
            } else {
                $score -= 50;
            }
        }

        if (!empty($categories)) {
            $matched = count(array_intersect($game->getCategories(), $categories));
            $score += $matched * 10;
        }

        if (!empty($mechanics)) {
            $matched = count(array_intersect($game->getMechanics(), $mechanics));
            $score += $matched * 8;
        }

        if ($game->getComplexity() >= 1 && $game->getComplexity() <= 5) {
            $score += 2;
        }

        return $score;
    }

    private function buildReason(Game $game, ?int $players, ?int $maxTime, array $categories): string
    {
        $parts = [];

        if ($players !== null && $players >= $game->getMinPlayers() && $players <= $game->getMaxPlayers()) {
            $parts[] = "parfait pour {$players} joueurs";
        }

        if ($maxTime !== null && $game->getPlayingTime() > 0 && $game->getPlayingTime() <= $maxTime) {
            $parts[] = "dure {$game->getPlayingTime()} min (dans votre limite)";
        }

        if ($game->getRatingBgg() !== null && $game->getRatingBgg() >= 7.5) {
            $rating = number_format($game->getRatingBgg(), 1, '.', '');
            $parts[] = "noté {$rating}/10 sur BGG";
        }

        if (!empty($categories)) {
            $matched = array_intersect($game->getCategories(), $categories);
            if (!empty($matched)) {
                $parts[] = 'catégorie : ' . implode(', ', array_slice($matched, 0, 2));
            }
        }

        if (empty($parts)) {
            $parts[] = 'jeu populaire de votre ludothèque';
        }

        return 'Recommandé car ' . implode(', ', $parts);
    }

    private function buildForgottenReason(Game $game): string
    {
        $rating = $game->getRatingBgg() !== null ? number_format($game->getRatingBgg(), 1, '.', '') : null;
        if ($rating) {
            return "Jeu bien noté ({$rating}/10) que vous n'avez pas joué récemment";
        }
        return "Jeu de votre collection que vous avez peut-être oublié";
    }
}
