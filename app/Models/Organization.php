<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['modules' => 'array', 'active' => 'boolean', 'delivery_fee' => 'integer'];
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function enabled(string $module): bool
    {
        return in_array($module, $this->modules ?? []);
    }
}
