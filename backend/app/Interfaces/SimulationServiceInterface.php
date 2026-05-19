<?php

namespace App\Interfaces;

use App\Models\GameMatch;
use App\DTOs\MatchResultDTO;

interface SimulationServiceInterface
{
    /**
     * Simulate a single match and return the result.
     */
    public function simulateMatch(GameMatch $match): MatchResultDTO;

    /**
     * Simulate all matches in a given week.
     */
    public function simulateWeek(int $week): void;

    /**
     * Simulate all remaining unplayed matches.
     */
    public function simulateAll(): void;

    /**
     * Get the current week number (first unplayed week).
     */
    public function getCurrentWeek(): int;
}
