<?php

namespace App\Models;

class Campaign extends BusinessModel
{
    protected function casts(): array
    {
        return ['active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
