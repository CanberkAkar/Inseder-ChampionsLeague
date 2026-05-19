<?php

namespace Tests\Unit;

use App\Models\GameMatch;
use App\Models\Standing;
use App\Models\Team;
use App\Services\PredictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PredictionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PredictionService $predictionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->predictionService = new PredictionService();
    }

    /** @test */
    public function it_returns_probabilities_for_all_teams(): void
    {
        $teams = $this->seedTeamsAndMatches();

        $predictions = $this->predictionService->calculateProbabilities(100);

        $this->assertCount(4, $predictions);
    }

    /** @test */
    public function probabilities_sum_to_100_percent(): void
    {
        $this->seedTeamsAndMatches();

        $predictions = $this->predictionService->calculateProbabilities(100);
        $total = $predictions->sum('probability');

        // Allow small floating point variance
        $this->assertEqualsWithDelta(100.0, $total, 1.0);
    }

    /** @test */
    public function team_with_insurmountable_lead_gets_100_percent(): void
    {
        [$teamA, $teamB, $teamC, $teamD] = $this->seedTeams();

        // Team A has 100 points, no remaining matches
        Standing::where('team_id', $teamA->id)->update(['points' => 100]);
        Standing::where('team_id', $teamB->id)->update(['points' => 0]);
        Standing::where('team_id', $teamC->id)->update(['points' => 0]);
        Standing::where('team_id', $teamD->id)->update(['points' => 0]);

        // No remaining matches
        $predictions = $this->predictionService->calculateProbabilities(100);

        $leaderProb = $predictions->firstWhere('team_id', $teamA->id)['probability'];
        $this->assertEquals(100.0, $leaderProb);
    }

    /** @test */
    public function should_show_predictions_is_false_at_start(): void
    {
        $this->seedTeamsAndMatches();

        // Week 1 is current → 6 weeks remaining → should NOT show
        $shouldShow = $this->predictionService->shouldShowPredictions();
        $this->assertFalse($shouldShow);
    }

    /** @test */
    public function should_show_predictions_is_true_in_last_3_weeks(): void
    {
        $this->seedTeamsAndMatches();

        // Mark first 3 weeks as played
        GameMatch::where('week', '<=', 3)->update(['is_played' => true, 'home_goals' => 1, 'away_goals' => 0]);

        $shouldShow = $this->predictionService->shouldShowPredictions();
        $this->assertTrue($shouldShow);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function seedTeams(): array
    {
        $teams = [];
        $data  = [
            ['Manchester City',      'MCI', 92, '#6CABDD'],
            ['Real Madrid',          'RMA', 90, '#FEBE10'],
            ['Bayern Munich',        'BAY', 88, '#DC052D'],
            ['Paris Saint-Germain',  'PSG', 85, '#004170'],
        ];

        foreach ($data as [$name, $short, $power, $color]) {
            $team = Team::create(['name' => $name, 'short_name' => $short, 'power' => $power, 'logo_color' => $color]);
            Standing::create(['team_id' => $team->id]);
            $teams[] = $team;
        }

        return $teams;
    }

    private function seedTeamsAndMatches(): array
    {
        $teams = $this->seedTeams();
        [$a, $b, $c, $d] = $teams;

        $fixtures = [
            [1, $a->id, $b->id], [1, $c->id, $d->id],
            [2, $a->id, $c->id], [2, $b->id, $d->id],
            [3, $a->id, $d->id], [3, $b->id, $c->id],
            [4, $b->id, $a->id], [4, $d->id, $c->id],
            [5, $c->id, $a->id], [5, $d->id, $b->id],
            [6, $d->id, $a->id], [6, $c->id, $b->id],
        ];

        foreach ($fixtures as [$week, $home, $away]) {
            GameMatch::create([
                'week' => $week, 'home_team_id' => $home,
                'away_team_id' => $away, 'is_played' => false,
            ]);
        }

        return $teams;
    }
}
