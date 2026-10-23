<?php

namespace App\Models;

class TailoringJob extends BusinessModel
{
    protected function casts(): array
    {
        return ['measurements' => 'array', 'due_date' => 'date'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
