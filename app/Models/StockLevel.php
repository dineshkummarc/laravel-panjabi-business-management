<?php

namespace App\Models;

class StockLevel extends BusinessModel
{
    protected function casts(): array
    {
        return ['quantity' => 'integer'];
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
