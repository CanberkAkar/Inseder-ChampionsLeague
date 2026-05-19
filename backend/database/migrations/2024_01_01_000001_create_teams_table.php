<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('short_name', 3)->unique();
            $table->unsignedTinyInteger('power')->comment('Team strength 1-100');
            $table->string('logo_color', 7)->default('#3B82F6')->comment('Hex color for logo');
            $table->string('logo_url')->nullable()->comment('Path to the team logo asset');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
