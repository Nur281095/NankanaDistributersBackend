<?php

namespace App\Models;

use App\Enums\HomeLinkType;
use App\Models\Concerns\CleansPublicMedia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'home_slider_id',
    'image',
    'title',
    'subtitle',
    'link_type',
    'link_value',
    'sort_order',
    'is_active',
])]
class HomeSliderSlide extends Model
{
    use CleansPublicMedia;

    protected static function booted(): void
    {
        static::saving(function (HomeSliderSlide $slide): void {
            if ($slide->link_type === HomeLinkType::None) {
                $slide->link_value = null;
            }
        });
    }

    /**
     * @return list<string>
     */
    protected function publicMediaAttributes(): array
    {
        return ['image'];
    }

    /**
     * @return BelongsTo<HomeSlider, $this>
     */
    public function slider(): BelongsTo
    {
        return $this->belongsTo(HomeSlider::class, 'home_slider_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'link_type' => HomeLinkType::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
