<?php

namespace App\Models;

class Expense extends BusinessModel
{
    protected function casts(): array
    {
        return [];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
