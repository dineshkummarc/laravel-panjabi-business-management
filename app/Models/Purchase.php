<?php

namespace App\Models;

class Purchase extends BusinessModel
{
    protected function casts(): array
    {
        return [];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
