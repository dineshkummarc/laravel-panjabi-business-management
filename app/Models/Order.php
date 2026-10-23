<?php

namespace App\Models;

class Order extends BusinessModel
{
    protected function casts(): array
    {
        return ['total' => 'integer', 'subtotal' => 'integer', 'discount' => 'integer', 'shipping' => 'integer'];
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function getPaidAttribute(): int
    {
        return (int) $this->payments()->sum('amount');
    }
}
