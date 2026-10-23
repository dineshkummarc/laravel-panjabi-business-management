<?php

namespace App\Models;

class StockMovement extends BusinessModel
{
    protected function casts(): array
    {
        return [];
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
