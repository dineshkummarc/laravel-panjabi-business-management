<?php

namespace App\Models;

class Transfer extends BusinessModel
{
    protected function casts(): array
    {
        return [];
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function source()
    {
        return $this->belongsTo(Branch::class, 'source_id');
    }

    public function destination()
    {
        return $this->belongsTo(Branch::class, 'destination_id');
    }
}
