<?php

namespace App\Interfaces;

use Illuminate\Support\Collection;

interface FixtureServiceInterface
{
    /**
     * Generate a full round-robin fixture for all teams.
     * Each team plays home and away against every other team.
     */
    public function generate(): void;

    /**
     * Check if a fixture has already been generated.
     */
    public function isGenerated(): bool;

    /**
     * Get the total number of weeks in the fixture.
     */
    public function getTotalWeeks(): int;

    /**
     * Get matches for a specific week.
     */
    public function getMatchesByWeek(int $week): Collection;
}
