<?php

namespace App\Models;

class ProductionBatch extends BusinessModel
{
    protected function casts(): array
    {
        return [];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function material()
    {
        return $this->belongsTo(Variant::class, 'material_id');
    }
}
