<?php

use App\Http\Controllers\LeagueController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\PredictionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Champions League API Routes
|--------------------------------------------------------------------------
| These routes are loaded by bootstrap/app.php with apiPrefix: 'api'
| So the actual URLs will be: /api/league, /api/matches, etc.
*/

// League
Route::get('/league',        [LeagueController::class, 'index']);
Route::get('/teams',         [LeagueController::class, 'teams']);
Route::post('/league/reset', [LeagueController::class, 'reset']);

// Matches
Route::get('/matches',              [MatchController::class, 'index']);
Route::get('/matches/week/{week}',  [MatchController::class, 'byWeek']);
Route::post('/matches/play-week',   [MatchController::class, 'playWeek']);
Route::post('/matches/play-all',    [MatchController::class, 'playAll']);
Route::put('/matches/{id}',         [MatchController::class, 'update']);

// Predictions
Route::get('/predictions', [PredictionController::class, 'index']);
