<?php

namespace Tests\Unit;

use App\Models\GameMatch;
use App\Models\Standing;
use App\Models\Team;
use App\Services\FixtureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixtureServiceTest extends TestCase
{
    use RefreshDatabase;

    private FixtureService $fixtureService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureService = new FixtureService();
        $this->seedTeams();
    }

    /** @test */
    public function it_generates_correct_number_of_matches(): void
    {
        $this->fixtureService->generate();

        // 4 teams, round-robin home & away → 4*3/2 * 2 = 12 matches
        $this->assertEquals(12, GameMatch::count());
    }

    /** @test */
    public function it_generates_correct_number_of_weeks(): void
    {
        $this->fixtureService->generate();

        $weeks = GameMatch::distinct()->pluck('week')->count();
        $this->assertEquals(6, $weeks);
    }

    /** @test */
    public function each_week_has_two_matches(): void
    {
        $this->fixtureService->generate();

        for ($week = 1; $week <= 6; $week++) {
            $count = GameMatch::where('week', $week)->count();
            $this->assertEquals(2, $count, "Week {$week} should have 2 matches");
        }
    }

    /** @test */
    public function each_team_plays_exactly_six_matches(): void
    {
        $this->fixtureService->generate();

        $teams = Team::all();

        foreach ($teams as $team) {
            $homeMatches = GameMatch::where('home_team_id', $team->id)->count();
            $awayMatches = GameMatch::where('away_team_id', $team->id)->count();

            $this->assertEquals(6, $homeMatches + $awayMatches, "{$team->name} should play 6 matches");
        }
    }

    /** @test */
    public function each_team_plays_both_home_and_away_against_each_opponent(): void
    {
        $this->fixtureService->generate();

        $teams = Team::all();

        foreach ($teams as $teamA) {
            foreach ($teams as $teamB) {
                if ($teamA->id === $teamB->id) {
                    continue;
                }

                $homeGame = GameMatch::where('home_team_id', $teamA->id)
                    ->where('away_team_id', $teamB->id)
                    ->count();

                $this->assertEquals(1, $homeGame, "{$teamA->name} should play once at home vs {$teamB->name}");
            }
        }
    }

    /** @test */
    public function generate_is_idempotent(): void
    {
        $this->fixtureService->generate();
        $this->fixtureService->generate(); // Second call should not add more matches

        $this->assertEquals(12, GameMatch::count());
    }

    /** @test */
    public function is_generated_returns_false_when_no_matches(): void
    {
        $this->assertFalse($this->fixtureService->isGenerated());
    }

    /** @test */
    public function is_generated_returns_true_after_generation(): void
    {
        $this->fixtureService->generate();
        $this->assertTrue($this->fixtureService->isGenerated());
    }

    private function seedTeams(): void
    {
        $teams = [
            ['name' => 'Manchester City', 'short_name' => 'MCI', 'power' => 92, 'logo_color' => '#6CABDD'],
            ['name' => 'Real Madrid',     'short_name' => 'RMA', 'power' => 90, 'logo_color' => '#FEBE10'],
            ['name' => 'Bayern Munich',   'short_name' => 'BAY', 'power' => 88, 'logo_color' => '#DC052D'],
            ['name' => 'PSG',             'short_name' => 'PSG', 'power' => 85, 'logo_color' => '#004170'],
        ];

        foreach ($teams as $team) {
            $t = Team::create($team);
            Standing::create(['team_id' => $t->id]);
        }
    }
}
