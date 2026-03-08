<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RichLifeVision extends Model
{
    /** @use HasFactory<\Database\Factories\RichLifeVisionFactory> */
    use HasFactory;

    protected $fillable = [
        'text',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
