<?php

namespace App\Models;

class Payment extends BusinessModel
{
    protected function casts(): array
    {
        return [];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
