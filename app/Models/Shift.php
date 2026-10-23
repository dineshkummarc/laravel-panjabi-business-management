<?php

namespace App\Models;

class Shift extends BusinessModel
{
    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
