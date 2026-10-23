<?php

namespace App\Models;

use Illuminate\Validation\ValidationException;

class Variant extends BusinessModel
{
    protected static function boot(): void
    {
        parent::boot();
        static::updating(function ($variant) {
            if ($variant->isDirty('cost') && StockMovement::where('variant_id', $variant->id)->exists()) {
                throw ValidationException::withMessages(['cost' => 'Standard cost is locked after stock movements. A revaluation workflow is required to change it.']);
            }
        });
    }

    protected function casts(): array
    {
        return ['price' => 'integer', 'cost' => 'integer', 'wholesale_price' => 'integer'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stocks()
    {
        return $this->hasMany(StockLevel::class);
    }
}
