<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RichLifeVision extends Model
{
    /** @use HasFactory<\Database\Factories\RichLifeVisionFactory> */
    use HasFactory;

    protected $fillable = [
        'rich_life_vision_category_id',
        'text',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<RichLifeVisionCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(RichLifeVisionCategory::class, 'rich_life_vision_category_id');
    }
}
