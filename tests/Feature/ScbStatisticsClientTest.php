<?php

use App\Services\ScbStatisticsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('SCB JSON-stat values are normalized and aggregated into local points', function () {
    config([
        'statistics.scb_base_url' => 'https://scb.test',
        'statistics.series' => [[
            'slug' => 'befolkning',
            'path' => 'population',
            'title' => 'Sveriges befolkning',
            'short_title' => 'Befolkning',
            'description' => 'Folkmängd.',
            'unit' => 'personer',
            'frequency' => 'year',
            'category' => 'Befolkning',
            'chart_type' => 'area',
            'accent' => 'coral',
            'query' => [
                'Kon' => ['1', '2'],
                'Tid' => ['*'],
            ],
        ]],
    ]);

    Http::fake([
        'https://scb.test/population' => Http::response([
            'version' => '2.0',
            'class' => 'dataset',
            'label' => 'Folkmängd efter kön och år',
            'source' => 'SCB',
            'updated' => '2026-02-24T07:00:00Z',
            'role' => ['time' => ['Tid']],
            'id' => ['Kon', 'Tid'],
            'size' => [2, 2],
            'dimension' => [
                'Kon' => [
                    'category' => ['index' => ['1' => 0, '2' => 1]],
                ],
                'Tid' => [
                    'category' => ['index' => ['2024' => 0, '2025' => 1]],
                ],
            ],
            'value' => [100, 101, 110, 111],
        ]),
    ]);

    app(ScbStatisticsClient::class)->syncAll();

    $this->assertDatabaseHas('statistic_series', [
        'slug' => 'befolkning',
        'source_name' => 'SCB',
    ]);
    $this->assertDatabaseHas('statistic_points', [
        'period' => '2024',
        'geography' => 'SE',
        'geography_label' => 'Sverige',
        'value' => 210,
    ]);
    $this->assertDatabaseHas('statistic_points', [
        'period' => '2025',
        'value' => 212,
    ]);

    Http::assertSent(fn ($request) => $request->url() === 'https://scb.test/population'
        && $request['query'][1]['selection'] === [
            'filter' => 'all',
            'values' => ['*'],
        ]);
});

test('SCB school-year periods use their first year as the sorting date', function () {
    config([
        'statistics.scb_base_url' => 'https://scb.test',
        'statistics.series' => [[
            'slug' => 'utbildning',
            'path' => 'education',
            'title' => 'Utbildning',
            'short_title' => 'Utbildning',
            'description' => 'Utbildningsstatistik.',
            'unit' => 'personer',
            'frequency' => 'year',
            'category' => 'Utbildning',
            'chart_type' => 'line',
            'accent' => 'blue',
            'query' => ['Tid' => ['*']],
        ]],
    ]);

    Http::fake([
        'https://scb.test/education' => Http::response([
            'version' => '2.0',
            'class' => 'dataset',
            'label' => 'Utbildning efter läsår',
            'source' => 'SCB',
            'updated' => '2026-01-01T00:00:00Z',
            'role' => ['time' => ['Tid']],
            'id' => ['Tid'],
            'size' => [1],
            'dimension' => [
                'Tid' => [
                    'category' => ['index' => ['2014-2015' => 0]],
                ],
            ],
            'value' => [100],
        ]),
    ]);

    app(ScbStatisticsClient::class)->syncAll();

    $this->assertDatabaseHas('statistic_points', [
        'period' => '2014-2015',
        'period_start' => '2014-01-01',
        'value' => 100,
    ]);
});

test('SCB quarter and half-year periods use their first calendar date', function (string $frequency, string $period, string $expectedStart) {
    config([
        'statistics.scb_base_url' => 'https://scb.test',
        'statistics.series' => [[
            'slug' => "period-{$frequency}",
            'path' => 'periods',
            'title' => 'Perioder',
            'short_title' => 'Perioder',
            'description' => 'Periodstatistik.',
            'unit' => 'index',
            'frequency' => $frequency,
            'category' => 'Ekonomi',
            'chart_type' => 'line',
            'accent' => 'blue',
            'query' => ['Tid' => ['*']],
        ]],
    ]);

    Http::fake([
        'https://scb.test/periods' => Http::response([
            'version' => '2.0',
            'class' => 'dataset',
            'label' => 'Perioder',
            'source' => 'SCB',
            'updated' => '2026-01-01T00:00:00Z',
            'role' => ['time' => ['Tid']],
            'id' => ['Tid'],
            'size' => [1],
            'dimension' => [
                'Tid' => [
                    'category' => ['index' => [$period => 0]],
                ],
            ],
            'value' => [100],
        ]),
    ]);

    app(ScbStatisticsClient::class)->syncAll();

    $this->assertDatabaseHas('statistic_points', [
        'period' => $period,
        'period_start' => $expectedStart,
    ]);
})->with([
    'first quarter' => ['quarter', '2025K1', '2025-01-01'],
    'fourth quarter' => ['quarter', '2025K4', '2025-10-01'],
    'first half-year' => ['half_year', '2025H1', '2025-01-01'],
    'second half-year' => ['half_year', '2025H2', '2025-07-01'],
]);

test('SCB replacement tables are merged with their historical series', function () {
    config([
        'statistics.scb_base_url' => 'https://scb.test',
        'statistics.series' => [[
            'slug' => 'flyttningsoverskott',
            'path' => 'current-migration',
            'title' => 'Flyttningsöverskott',
            'short_title' => 'Flyttningsöverskott',
            'description' => 'Flyttningsöverskott per månad.',
            'unit' => 'personer',
            'frequency' => 'month',
            'category' => 'Befolkning',
            'chart_type' => 'line',
            'accent' => 'blue',
            'query' => ['Tid' => ['*']],
            'additional_sources' => [[
                'path' => 'historical-migration',
                'query' => ['Tid' => ['*']],
            ]],
        ]],
    ]);

    $dataset = fn (string $period, float $value): array => [
        'version' => '2.0',
        'class' => 'dataset',
        'label' => 'Flyttningsöverskott per månad',
        'source' => 'SCB',
        'updated' => '2026-01-01T00:00:00Z',
        'role' => ['time' => ['Tid']],
        'id' => ['Tid'],
        'size' => [1],
        'dimension' => [
            'Tid' => [
                'category' => ['index' => [$period => 0]],
            ],
        ],
        'value' => [$value],
    ];

    Http::fake([
        'https://scb.test/historical-migration' => Http::response(
            $dataset('2024M12', 120),
        ),
        'https://scb.test/current-migration' => Http::response(
            $dataset('2025M01', 140),
        ),
    ]);

    app(ScbStatisticsClient::class)->syncAll();

    $this->assertDatabaseHas('statistic_points', [
        'period' => '2024M12',
        'value' => 120,
        'sort_order' => 0,
    ]);
    $this->assertDatabaseHas('statistic_points', [
        'period' => '2025M01',
        'value' => 140,
        'sort_order' => 1,
    ]);
});

test('multiple SCB queries are stored as separate lines in one chart', function () {
    config([
        'statistics.scb_base_url' => 'https://scb.test',
        'statistics.series' => [[
            'slug' => 'skatt-inkomstgrupp',
            'path' => 'taxes',
            'title' => 'Skatt',
            'short_title' => 'Skatt',
            'description' => 'Skatt per komponent.',
            'unit' => 'tusen kr',
            'frequency' => 'year',
            'category' => 'Hushållsekonomi',
            'chart_type' => 'line',
            'accent' => 'ochre',
            'lines' => [
                [
                    'code' => 'SE',
                    'label' => 'Slutlig skatt',
                    'query' => ['Skatter' => ['sslut'], 'Tid' => ['*']],
                ],
                [
                    'code' => 'MUNICIPAL',
                    'label' => 'Kommunal inkomstskatt',
                    'query' => ['Skatter' => ['sklfvi'], 'Tid' => ['*']],
                ],
            ],
        ]],
    ]);

    $dataset = fn (float $value): array => [
        'version' => '2.0',
        'class' => 'dataset',
        'label' => 'Skatt efter typ',
        'source' => 'SCB',
        'updated' => '2026-01-01T00:00:00Z',
        'role' => ['time' => ['Tid']],
        'id' => ['Tid'],
        'size' => [1],
        'dimension' => [
            'Tid' => [
                'category' => ['index' => ['2024' => 0]],
            ],
        ],
        'value' => [$value],
    ];

    Http::fake(fn ($request) => Http::response(
        $dataset($request['query'][0]['selection']['values'][0] === 'sslut' ? 120 : 95),
    ));

    app(ScbStatisticsClient::class)->syncAll();

    $this->assertDatabaseHas('statistic_points', [
        'geography' => 'SE',
        'geography_label' => 'Slutlig skatt',
        'period' => '2024',
        'value' => 120,
    ]);
    $this->assertDatabaseHas('statistic_points', [
        'geography' => 'MUNICIPAL',
        'geography_label' => 'Kommunal inkomstskatt',
        'period' => '2024',
        'value' => 95,
    ]);
});

test('SCB monthly debt is calculated as a percentage of annual GDP', function () {
    config([
        'statistics.scb_base_url' => 'https://scb.test',
        'statistics.series' => [[
            'slug' => 'statsskuld-andel-bnp',
            'path' => 'debt',
            'title' => 'Statsskuld i förhållande till BNP',
            'short_title' => 'Statsskuld mot BNP',
            'description' => 'Statsskuld vid årets slut som andel av BNP.',
            'unit' => 'procent av BNP',
            'frequency' => 'year',
            'category' => 'Offentlig ekonomi',
            'chart_type' => 'line',
            'accent' => 'plum',
            'calculation' => [
                'type' => 'percentage_ratio',
                'numerator' => [
                    'path' => 'debt',
                    'frequency' => 'month',
                    'query' => ['Tid' => ['*']],
                ],
                'denominator' => [
                    'path' => 'gdp',
                    'frequency' => 'year',
                    'query' => ['Tid' => ['*']],
                ],
            ],
        ]],
    ]);

    $dataset = fn (array $periods, array $values): array => [
        'version' => '2.0',
        'class' => 'dataset',
        'label' => 'SCB-serie',
        'source' => 'SCB',
        'updated' => '2026-01-01T00:00:00Z',
        'role' => ['time' => ['Tid']],
        'id' => ['Tid'],
        'size' => [count($periods)],
        'dimension' => [
            'Tid' => [
                'category' => ['index' => array_flip($periods)],
            ],
        ],
        'value' => $values,
    ];

    Http::fake([
        'https://scb.test/debt' => Http::response(
            $dataset(['2023M12', '2024M06', '2024M12', '2025M01'], [1000, 1100, 1200, 1300]),
        ),
        'https://scb.test/gdp' => Http::response(
            $dataset(['2023', '2024', '2025'], [5000, 6000, 6500]),
        ),
    ]);

    app(ScbStatisticsClient::class)->syncAll();

    $this->assertDatabaseHas('statistic_points', [
        'period' => '2023',
        'value' => 20,
    ]);
    $this->assertDatabaseHas('statistic_points', [
        'period' => '2024',
        'value' => 20,
    ]);
    $this->assertDatabaseMissing('statistic_points', [
        'period' => '2025',
    ]);
});

test('a failed SCB request does not replace stored data', function () {
    config([
        'statistics.scb_base_url' => 'https://scb.test',
        'statistics.series' => [[
            'slug' => 'arbetsloshet',
            'path' => 'unemployment',
            'title' => 'Arbetslöshet',
            'short_title' => 'Arbetslöshet',
            'description' => 'Andel arbetslösa.',
            'unit' => 'procent',
            'frequency' => 'year',
            'category' => 'Arbetsmarknad',
            'chart_type' => 'line',
            'accent' => 'blue',
            'query' => ['Tid' => ['*']],
        ]],
    ]);

    Http::fake([
        'https://scb.test/unemployment' => Http::response(['error' => 'Unavailable'], 503),
    ]);

    expect(fn () => app(ScbStatisticsClient::class)->syncAll())->toThrow(
        RequestException::class,
    );
    $this->assertDatabaseMissing('statistic_series', ['slug' => 'arbetsloshet']);
});
