<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property CarbonImmutable $period_start */
class StatisticPoint extends Model
{
    protected $fillable = [
        'geography',
        'geography_label',
        'period',
        'period_start',
        'value',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_date',
            'value' => 'float',
        ];
    }

    /** @return BelongsTo<StatisticSeries, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(StatisticSeries::class, 'statistic_series_id');
    }
}
