<?php

namespace Database\Seeders;

use App\Models\Standing;
use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /**
     * Champions League teams with realistic power ratings.
     * Power scale: 1-100
     */
    public function run(): void
    {
        $teams = [
            [
                'name'       => 'Manchester City',
                'short_name' => 'MCI',
                'power'      => 92,
                'logo_color' => '#6CABDD',
                'logo_url'   => '/logos/manchester_city.png',
            ],
            [
                'name'       => 'Real Madrid',
                'short_name' => 'RMA',
                'power'      => 90,
                'logo_color' => '#FEBE10',
                'logo_url'   => '/logos/real_madrid.png',
            ],
            [
                'name'       => 'Bayern Munich',
                'short_name' => 'BAY',
                'power'      => 88,
                'logo_color' => '#DC052D',
                'logo_url'   => '/logos/bayern_munich.png',
            ],
            [
                'name'       => 'Paris Saint-Germain',
                'short_name' => 'PSG',
                'power'      => 85,
                'logo_color' => '#004170',
                'logo_url'   => '/logos/psg.png',
            ],
        ];

        foreach ($teams as $teamData) {
            $team = Team::updateOrCreate(
                ['name' => $teamData['name']],   // arama kriteri
                $teamData                         // güncellenecek/oluşturulacak değerler
            );

            // Create initial standing record only if it doesn't exist
            Standing::firstOrCreate(['team_id' => $team->id]);
        }
    }
}
