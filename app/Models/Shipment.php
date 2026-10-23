<?php

namespace App\Models;

class Shipment extends BusinessModel
{
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
