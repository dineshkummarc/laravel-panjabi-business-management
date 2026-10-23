<?php

namespace App\Models;

use Illuminate\Support\Facades\Storage;

class Product extends BusinessModel
{
    public function getImageUrlAttribute(): string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : ($this->image ?: '/images/panjabi-sage.svg');
    }

    protected function casts(): array
    {
        return ['active' => 'boolean', 'featured' => 'boolean'];
    }

    public function variants()
    {
        return $this->hasMany(Variant::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
