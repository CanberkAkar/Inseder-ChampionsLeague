<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('teams', 'logo_url')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->string('logo_url')->nullable()->after('logo_color')->comment('Path to the team logo asset');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('teams', 'logo_url')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->dropColumn('logo_url');
            });
        }
    }
};
