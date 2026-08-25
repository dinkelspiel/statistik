<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statistic_series', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('source_name');
            $table->text('source_url');
            $table->string('title');
            $table->string('short_title');
            $table->text('description');
            $table->string('unit');
            $table->string('frequency');
            $table->string('category');
            $table->string('chart_type');
            $table->string('accent');
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('statistic_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statistic_series_id')->constrained()->cascadeOnDelete();
            $table->string('period');
            $table->date('period_start');
            $table->double('value');
            $table->unsignedInteger('sort_order');
            $table->timestamps();

            $table->unique(['statistic_series_id', 'period']);
            $table->index(['statistic_series_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statistic_points');
        Schema::dropIfExists('statistic_series');
    }
};
