<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable|null $source_updated_at
 * @property CarbonImmutable|null $synced_at
 */
class StatisticSeries extends Model
{
    protected $fillable = [
        'slug',
        'source_name',
        'source_url',
        'title',
        'short_title',
        'description',
        'unit',
        'frequency',
        'category',
        'chart_type',
        'accent',
        'source_updated_at',
        'synced_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'source_updated_at' => 'immutable_datetime',
            'synced_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /** @return HasMany<StatisticPoint, $this> */
    public function points(): HasMany
    {
        return $this->hasMany(StatisticPoint::class)->orderBy('sort_order');
    }
}
