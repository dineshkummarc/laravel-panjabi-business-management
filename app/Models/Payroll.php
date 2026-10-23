<?php

namespace App\Models;

class Payroll extends BusinessModel
{
    protected function casts(): array
    {
        return [];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
