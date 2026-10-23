<?php

namespace App\Models;

class PaymentAttempt extends BusinessModel
{
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
