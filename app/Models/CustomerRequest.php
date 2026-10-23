<?php

namespace App\Models;

class CustomerRequest extends BusinessModel
{
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
