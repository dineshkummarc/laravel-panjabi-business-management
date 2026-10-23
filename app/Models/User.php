<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'organization_id', 'branch_id', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->organization_id !== null && $this->role !== 'customer' && $this->organization->active;
    }

    public function allows(string $ability): bool
    {
        if (in_array($this->role, ['owner', 'admin'])) {
            return true;
        }

        return in_array($ability, match ($this->role) {
            'manager' => ['catalog', 'inventory', 'orders', 'pos', 'purchasing', 'crm', 'reports', 'tailoring', 'production', 'shifts'],
            'cashier' => ['orders', 'pos', 'crm', 'shifts'],
            'inventory' => ['catalog', 'inventory', 'purchasing'],
            'accountant' => ['accounting', 'reports'],
            'production' => ['production', 'tailoring'],
            'hr' => ['hr', 'attendance'],
            default => [],
        });
    }
}
