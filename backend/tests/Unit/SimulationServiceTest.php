<?php

namespace Tests\Unit;

use App\DTOs\MatchResultDTO;
use App\Models\GameMatch;
use App\Models\Team;
use App\Services\SimulationService;
use App\Services\StandingService;
use Mockery;
use Tests\TestCase;

class SimulationServiceTest extends TestCase
{
    private SimulationService $simulationService;

    protected function setUp(): void
    {
        parent::setUp();

        $standingService = Mockery::mock(StandingService::class);
        $standingService->shouldReceive('updateFromMatch')->andReturn(null);

        $this->simulationService = new SimulationService($standingService);
    }

    /** @test */
    public function it_returns_a_match_result_dto(): void
    {
        $homeTeam = new Team(['name' => 'Team A', 'power' => 90]);
        $awayTeam = new Team(['name' => 'Team B', 'power' => 70]);

        $match = Mockery::mock(GameMatch::class)->makePartial();
        $match->shouldReceive('getAttribute')->with('homeTeam')->andReturn($homeTeam);
        $match->shouldReceive('getAttribute')->with('awayTeam')->andReturn($awayTeam);

        $result = $this->simulationService->simulateMatch($match);

        $this->assertInstanceOf(MatchResultDTO::class, $result);
        $this->assertIsInt($result->homeGoals);
        $this->assertIsInt($result->awayGoals);
        $this->assertGreaterThanOrEqual(0, $result->homeGoals);
        $this->assertGreaterThanOrEqual(0, $result->awayGoals);
    }

    /** @test */
    public function stronger_team_wins_more_often_over_many_simulations(): void
    {
        $strongTeam = new Team(['name' => 'Strong', 'power' => 99]);
        $weakTeam   = new Team(['name' => 'Weak',   'power' => 1]);

        $strongWins = 0;
        $total      = 200;

        for ($i = 0; $i < $total; $i++) {
            $match = Mockery::mock(GameMatch::class)->makePartial();
            $match->shouldReceive('getAttribute')->with('homeTeam')->andReturn($strongTeam);
            $match->shouldReceive('getAttribute')->with('awayTeam')->andReturn($weakTeam);

            $result = $this->simulationService->simulateMatch($match);

            if ($result->homeGoals > $result->awayGoals) {
                $strongWins++;
            }
        }

        // Strong team should win at least 60% of the time
        $this->assertGreaterThan($total * 0.60, $strongWins);
    }

    /** @test */
    public function match_result_dto_correctly_identifies_winner(): void
    {
        $homeWin = new MatchResultDTO(3, 1);
        $this->assertEquals('home', $homeWin->getWinner());

        $awayWin = new MatchResultDTO(0, 2);
        $this->assertEquals('away', $awayWin->getWinner());

        $draw = new MatchResultDTO(1, 1);
        $this->assertEquals('draw', $draw->getWinner());
    }

    /** @test */
    public function goals_are_never_negative(): void
    {
        $teamA = new Team(['name' => 'A', 'power' => 50]);
        $teamB = new Team(['name' => 'B', 'power' => 50]);

        for ($i = 0; $i < 100; $i++) {
            $match = Mockery::mock(GameMatch::class)->makePartial();
            $match->shouldReceive('getAttribute')->with('homeTeam')->andReturn($teamA);
            $match->shouldReceive('getAttribute')->with('awayTeam')->andReturn($teamB);

            $result = $this->simulationService->simulateMatch($match);

            $this->assertGreaterThanOrEqual(0, $result->homeGoals);
            $this->assertGreaterThanOrEqual(0, $result->awayGoals);
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
