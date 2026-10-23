<?php

namespace App\Models;

class OrderItem extends BusinessModel
{
    protected function casts(): array
    {
        return [];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }
}
