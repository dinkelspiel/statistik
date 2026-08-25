<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statistic_points', function (Blueprint $table) {
            $table->dropUnique(['statistic_series_id', 'period']);
            $table->string('geography')->default('SE')->after('statistic_series_id');
            $table->string('geography_label')->default('Sverige')->after('geography');
            $table->unique(['statistic_series_id', 'geography', 'period']);
        });
    }

    public function down(): void
    {
        Schema::table('statistic_points', function (Blueprint $table) {
            $table->dropUnique(['statistic_series_id', 'geography', 'period']);
            $table->dropColumn(['geography', 'geography_label']);
            $table->unique(['statistic_series_id', 'period']);
        });
    }
};
