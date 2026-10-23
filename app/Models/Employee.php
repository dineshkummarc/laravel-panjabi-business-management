<?php

namespace App\Models;

class Employee extends BusinessModel
{
    protected function casts(): array
    {
        return ['salary' => 'integer'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
