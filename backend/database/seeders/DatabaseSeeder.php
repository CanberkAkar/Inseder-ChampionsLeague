<?php

namespace Database\Seeders;

use App\Interfaces\FixtureServiceInterface;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(TeamSeeder::class);

        // Generate the fixture after teams are seeded
        $fixtureService = app(FixtureServiceInterface::class);
        $fixtureService->generate();
    }
}
