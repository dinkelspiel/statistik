<?php

use App\Services\WorldBankStatisticsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('World Bank observations are stored as comparable geography series', function () {
    config([
        'statistics.world_bank_base_url' => 'https://world.test/v2',
        'statistics.series' => [[
            'slug' => 'jamforelse-arbetsloshet',
            'source' => 'world_bank',
            'indicator' => 'SL.UEM.TOTL.ZS',
            'title' => 'Arbetslöshet i omvärlden',
            'short_title' => 'Arbetslöshet',
            'description' => 'Jämförbar arbetslöshet.',
            'unit' => 'procent',
            'frequency' => 'year',
            'category' => 'Internationellt',
            'chart_type' => 'line',
            'accent' => 'blue',
        ]],
    ]);

    Http::fake(function ($request) {
        $country = match (true) {
            str_contains($request->url(), '/country/SE/') => ['Sweden', 8.4],
            str_contains($request->url(), '/country/EUU/') => ['European Union', 5.9],
            str_contains($request->url(), '/country/WLD/') => ['World', 4.8],
        };

        return Http::response([
            [
                'lastupdated' => '2026-07-13',
            ],
            [[
                'country' => ['value' => $country[0]],
                'date' => '2024',
                'value' => $country[1],
            ]],
        ]);
    });

    app(WorldBankStatisticsClient::class)->syncAll();

    $this->assertDatabaseHas('statistic_series', [
        'slug' => 'jamforelse-arbetsloshet',
        'source_name' => 'Världsbanken',
    ]);
    $this->assertDatabaseHas('statistic_points', [
        'geography' => 'SE',
        'geography_label' => 'Sverige',
        'period' => '2024',
        'value' => 8.4,
    ]);
    $this->assertDatabaseHas('statistic_points', [
        'geography' => 'EU',
        'geography_label' => 'EU',
        'period' => '2024',
        'value' => 5.9,
    ]);
    $this->assertDatabaseHas('statistic_points', [
        'geography' => 'WLD',
        'geography_label' => 'Världen',
        'period' => '2024',
        'value' => 4.8,
    ]);
});

test('a failed World Bank request does not create partial comparison data', function () {
    config([
        'statistics.world_bank_base_url' => 'https://world.test/v2',
        'statistics.series' => [[
            'slug' => 'jamforelse-inflation',
            'source' => 'world_bank',
            'indicator' => 'FP.CPI.TOTL.ZG',
            'title' => 'Inflation i omvärlden',
            'short_title' => 'Inflation',
            'description' => 'Jämförbar inflation.',
            'unit' => 'procent',
            'frequency' => 'year',
            'category' => 'Internationellt',
            'chart_type' => 'line',
            'accent' => 'plum',
        ]],
    ]);

    Http::fake([
        'https://world.test/v2/*' => Http::response(['error' => 'Unavailable'], 503),
    ]);

    expect(fn () => app(WorldBankStatisticsClient::class)->syncAll())->toThrow(
        RequestException::class,
    );
    $this->assertDatabaseMissing('statistic_series', [
        'slug' => 'jamforelse-inflation',
    ]);
});
