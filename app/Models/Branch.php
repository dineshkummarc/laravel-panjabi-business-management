<?php

namespace App\Models;

class Branch extends BusinessModel
{
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function stockLevels()
    {
        return $this->hasMany(StockLevel::class);
    }
}
