<?php

namespace App\Models;

use App\Enums\HomeLinkType;
use App\Models\Concerns\CleansPublicMedia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'home_section_id',
    'image',
    'title',
    'link_type',
    'link_value',
    'is_active',
])]
class HomeBanner extends Model
{
    use CleansPublicMedia;

    protected static function booted(): void
    {
        static::saving(function (HomeBanner $banner): void {
            if ($banner->link_type === HomeLinkType::None) {
                $banner->link_value = null;
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
     * @return BelongsTo<HomeSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(HomeSection::class, 'home_section_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'link_type' => HomeLinkType::class,
            'is_active' => 'boolean',
        ];
    }
}
