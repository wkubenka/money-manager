<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RichLifeVisionCategory extends Model
{
    /** @use HasFactory<\Database\Factories\RichLifeVisionCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<RichLifeVision, $this> */
    public function visions(): HasMany
    {
        return $this->hasMany(RichLifeVision::class)->orderBy('sort_order')->orderBy('id');
    }
}
