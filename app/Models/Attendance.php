<?php

namespace App\Models;

class Attendance extends BusinessModel
{
    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
