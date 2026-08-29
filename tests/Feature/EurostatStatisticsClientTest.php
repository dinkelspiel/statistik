<?php

use App\Services\EurostatStatisticsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function eurostatDefinition(): array
{
    return [
        'slug' => 'sveriges-statsbudget',
        'source' => 'eurostat',
        'dataset' => 'gov_10a_exp',
        'title' => 'Sveriges statsbudget – vart pengarna går',
        'short_title' => 'Statsbudgetens fördelning',
        'description' => 'Statliga utgifter efter ändamål.',
        'unit' => 'procent av statliga utgifter',
        'frequency' => 'year',
        'category' => 'Offentlig ekonomi',
        'chart_type' => 'area',
        'accent' => 'ochre',
        'query' => [
            'freq' => 'A',
            'unit' => 'MIO_NAC',
            'sector' => 'S1311',
            'na_item' => 'TE',
            'geo' => 'SE',
        ],
        'breakdown' => [
            'GF02' => 'Försvar',
            'GF10' => 'Socialt skydd och bidrag',
        ],
    ];
}

test('Eurostat government expenditure is stored as shares of annual total expenditure', function () {
    config([
        'statistics.eurostat_base_url' => 'https://eurostat.test/data',
        'statistics.series' => [eurostatDefinition()],
    ]);

    Http::fake([
        'https://eurostat.test/data/gov_10a_exp*' => Http::response([
            'version' => '2.0',
            'class' => 'dataset',
            'updated' => '2026-07-21T11:00:00+0200',
            'id' => ['cofog99', 'time'],
            'size' => [3, 2],
            'dimension' => [
                'cofog99' => [
                    'category' => [
                        'index' => ['TOTAL' => 0, 'GF02' => 1, 'GF10' => 2],
                    ],
                ],
                'time' => [
                    'category' => [
                        'index' => ['2023' => 0, '2024' => 1],
                    ],
                ],
            ],
            'value' => [1000, 1200, 50, 96, 300, 360],
        ]),
    ]);

    app(EurostatStatisticsClient::class)->syncAll();

    $this->assertDatabaseHas('statistic_series', [
        'slug' => 'sveriges-statsbudget',
        'source_name' => 'Eurostat',
        'chart_type' => 'area',
    ]);
    $this->assertDatabaseHas('statistic_points', [
        'geography' => 'GF02',
        'geography_label' => 'Försvar',
        'period' => '2023',
        'value' => 5,
    ]);
    $this->assertDatabaseHas('statistic_points', [
        'geography' => 'GF02',
        'period' => '2024',
        'value' => 8,
    ]);
    $this->assertDatabaseHas('statistic_points', [
        'geography' => 'GF10',
        'geography_label' => 'Socialt skydd och bidrag',
        'period' => '2024',
        'value' => 30,
    ]);

    Http::assertSent(fn($request) => $request->url() === 'https://eurostat.test/data/gov_10a_exp?freq=A&unit=MIO_NAC&sector=S1311&na_item=TE&geo=SE&lang=en');
});

test('a failed Eurostat request does not create partial expenditure data', function () {
    config([
        'statistics.eurostat_base_url' => 'https://eurostat.test/data',
        'statistics.series' => [eurostatDefinition()],
    ]);

    Http::fake([
        'https://eurostat.test/data/*' => Http::response(['error' => 'Unavailable'], 503),
    ]);

    expect(fn() => app(EurostatStatisticsClient::class)->syncAll())->toThrow(
        RequestException::class,
    );
    $this->assertDatabaseMissing('statistic_series', [
        'slug' => 'sveriges-statsbudget',
    ]);
});
