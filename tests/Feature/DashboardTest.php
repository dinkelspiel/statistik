<?php

use App\Models\StatisticSeries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the statistics dashboard is public', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('series', 0));
});

test('the dashboard returns locally stored official series', function () {
    $series = StatisticSeries::query()->create([
        'slug' => 'arbetsloshet',
        'source_name' => 'SCB',
        'source_url' => 'https://api.scb.se/example',
        'title' => 'Arbetslöshet',
        'short_title' => 'Arbetslöshet',
        'description' => 'Andel arbetslösa.',
        'unit' => 'procent',
        'frequency' => 'year',
        'category' => 'Arbetsmarknad',
        'chart_type' => 'line',
        'accent' => 'blue',
        'source_updated_at' => '2026-01-23 08:00:00',
        'synced_at' => '2026-08-05 12:00:00',
    ]);
    $series->points()->create([
        'period' => '2025',
        'period_start' => '2025-01-01',
        'value' => 8.8,
        'sort_order' => 0,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('series.0.slug', 'arbetsloshet')
            ->where('series.0.sourceName', 'SCB')
            ->where('series.0.explanation', config('statistic_explanations.arbetsloshet'))
            ->where('series.0.points.0.geography', 'SE')
            ->where('series.0.points.0.geographyLabel', 'Sverige')
            ->where('series.0.points.0.value', 8.8));
});

test('every configured statistic has a detailed explanation', function () {
    $catalogSlugs = collect(config('statistics.series'))->pluck('slug');
    $explanations = collect(config('statistic_explanations'));

    expect($catalogSlugs->diff($explanations->keys()))->toBeEmpty()
        ->and($explanations->keys()->diff($catalogSlugs))->toBeEmpty();

    $explanations->each(
        fn (mixed $explanation) => expect($explanation)
            ->toBeString()
            ->and(mb_strlen($explanation))
            ->toBeGreaterThan(200),
    );
});

test('the dashboard excludes series removed from the configured catalog', function () {
    StatisticSeries::query()->create([
        'slug' => 'removed-series',
        'source_name' => 'SCB',
        'source_url' => 'https://api.scb.se/example',
        'title' => 'Removed',
        'short_title' => 'Removed',
        'description' => 'No longer shown.',
        'unit' => 'procent',
        'frequency' => 'year',
        'category' => 'Utbildning',
        'chart_type' => 'line',
        'accent' => 'blue',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('series', 0));
});
