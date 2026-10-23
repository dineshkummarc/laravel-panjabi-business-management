<?php

namespace App\Filament\Resources\VariantResource\Pages;

use App\Filament\Resources\VariantResource;
use Filament\Actions\CreateAction;

class ListRecords extends \Filament\Resources\Pages\ListRecords
{
    protected static string $resource = VariantResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
