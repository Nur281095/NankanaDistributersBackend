<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'home_section_id',
    'autoplay',
    'interval_ms',
])]
class HomeSlider extends Model
{
    /**
     * @return BelongsTo<HomeSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(HomeSection::class, 'home_section_id');
    }

    /**
     * @return HasMany<HomeSliderSlide, $this>
     */
    public function slides(): HasMany
    {
        return $this->hasMany(HomeSliderSlide::class)->orderBy('sort_order');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'autoplay' => 'boolean',
            'interval_ms' => 'integer',
        ];
    }
}
