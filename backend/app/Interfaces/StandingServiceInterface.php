<?php

namespace App\Interfaces;

use App\Models\GameMatch;
use Illuminate\Support\Collection;

interface StandingServiceInterface
{
    /**
     * Update standings based on a played match result.
     */
    public function updateFromMatch(GameMatch $match): void;

    /**
     * Recalculate all standings from scratch based on played matches.
     */
    public function recalculateAll(): void;

    /**
     * Get standings ordered by points, goal difference, goals scored.
     */
    public function getOrderedStandings(): Collection;

    /**
     * Reset all standings to zero.
     */
    public function resetAll(): void;
}
