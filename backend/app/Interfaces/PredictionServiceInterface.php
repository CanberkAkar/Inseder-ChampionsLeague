<?php

namespace App\Interfaces;

use Illuminate\Support\Collection;

interface PredictionServiceInterface
{
    /**
     * Calculate championship probabilities for each team.
     * Uses Monte Carlo simulation over remaining matches.
     *
     * @param int $simulations Number of Monte Carlo iterations
     * @return Collection<int, array{team_id: int, team_name: string, probability: float}>
     */
    public function calculateProbabilities(int $simulations = 1000): Collection;

    /**
     * Check if predictions should be shown (last 3 weeks remaining).
     */
    public function shouldShowPredictions(): bool;
}
