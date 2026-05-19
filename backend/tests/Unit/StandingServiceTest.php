<?php

namespace Tests\Unit;

use App\DTOs\MatchResultDTO;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\Standing;
use App\Services\StandingService;

class StandingServiceTest extends TestCase
{
    use RefreshDatabase;

    private StandingService $standingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->standingService = new StandingService();
    }

    /** @test */
    public function it_awards_three_points_to_winner(): void
    {
        [$home, $away] = $this->createTeamsWithStandings();
        $match = $this->createPlayedMatch($home, $away, 2, 0);

        $this->standingService->updateFromMatch($match);

        $homeStanding = Standing::where('team_id', $home->id)->first();
        $awayStanding = Standing::where('team_id', $away->id)->first();

        $this->assertEquals(3, $homeStanding->points);
        $this->assertEquals(0, $awayStanding->points);
        $this->assertEquals(1, $homeStanding->won);
        $this->assertEquals(1, $awayStanding->lost);
    }

    /** @test */
    public function it_awards_one_point_each_for_draw(): void
    {
        [$home, $away] = $this->createTeamsWithStandings();
        $match = $this->createPlayedMatch($home, $away, 1, 1);

        $this->standingService->updateFromMatch($match);

        $homeStanding = Standing::where('team_id', $home->id)->first();
        $awayStanding = Standing::where('team_id', $away->id)->first();

        $this->assertEquals(1, $homeStanding->points);
        $this->assertEquals(1, $awayStanding->points);
        $this->assertEquals(1, $homeStanding->drawn);
        $this->assertEquals(1, $awayStanding->drawn);
    }

    /** @test */
    public function it_correctly_tracks_goals(): void
    {
        [$home, $away] = $this->createTeamsWithStandings();
        $match = $this->createPlayedMatch($home, $away, 3, 1);

        $this->standingService->updateFromMatch($match);

        $homeStanding = Standing::where('team_id', $home->id)->first();
        $awayStanding = Standing::where('team_id', $away->id)->first();

        $this->assertEquals(3, $homeStanding->goals_for);
        $this->assertEquals(1, $homeStanding->goals_against);
        $this->assertEquals(1, $awayStanding->goals_for);
        $this->assertEquals(3, $awayStanding->goals_against);
    }

    /** @test */
    public function it_orders_standings_by_points_then_goal_difference(): void
    {
        [$teamA, $teamB] = $this->createTeamsWithStandings();
        
        Standing::where('team_id', $teamA->id)->update(['points' => 6, 'goals_for' => 5, 'goals_against' => 1]);
        Standing::where('team_id', $teamB->id)->update(['points' => 6, 'goals_for' => 3, 'goals_against' => 2]);

        $standings = $this->standingService->getOrderedStandings();

        // Team A has better GD (+4 vs +1)
        $this->assertEquals($teamA->id, $standings->first()->team_id);
    }

    /** @test */
    public function reset_all_zeroes_all_standings(): void
    {
        [$teamA, $teamB] = $this->createTeamsWithStandings();

        Standing::where('team_id', $teamA->id)->update(['points' => 9, 'won' => 3, 'played' => 3]);

        $this->standingService->resetAll();

        $standing = Standing::where('team_id', $teamA->id)->first();
        $this->assertEquals(0, $standing->points);
        $this->assertEquals(0, $standing->won);
        $this->assertEquals(0, $standing->played);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function createTeamsWithStandings(): array
    {
        $teamA = Team::create(['name' => 'Team A', 'short_name' => 'TMA', 'power' => 80, 'logo_color' => '#FF0000']);
        $teamB = Team::create(['name' => 'Team B', 'short_name' => 'TMB', 'power' => 70, 'logo_color' => '#0000FF']);

        Standing::create(['team_id' => $teamA->id]);
        Standing::create(['team_id' => $teamB->id]);

        return [$teamA, $teamB];
    }

    private function createPlayedMatch(Team $home, Team $away, int $homeGoals, int $awayGoals): GameMatch
    {
        return GameMatch::create([
            'week'         => 1,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'home_goals'   => $homeGoals,
            'away_goals'   => $awayGoals,
            'is_played'    => true,
        ])->load(['homeTeam', 'awayTeam']);
    }
}
