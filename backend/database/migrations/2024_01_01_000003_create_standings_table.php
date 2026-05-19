<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained('teams')->cascadeOnDelete();
            $table->smallInteger('played')->default(0);
            $table->smallInteger('won')->default(0);
            $table->smallInteger('drawn')->default(0);
            $table->smallInteger('lost')->default(0);
            $table->smallInteger('goals_for')->default(0);
            $table->smallInteger('goals_against')->default(0);
            $table->smallInteger('points')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standings');
    }
};
