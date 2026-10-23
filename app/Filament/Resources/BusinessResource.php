<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

abstract class BusinessResource extends Resource
{
    protected static string $ability = 'catalog';

    protected static ?string $module = null;

    public static function canViewAny(): bool
    {
        return auth()->user()?->allows(static::$ability) ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny() && (! static::$module || auth()->user()->organization->enabled(static::$module));
    }

    public static function canEdit(Model $record): bool
    {
        return static::canCreate() && $record->organization_id === auth()->user()->organization_id;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
