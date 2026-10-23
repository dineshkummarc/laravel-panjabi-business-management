<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages\CreateRecord;
use App\Filament\Resources\CustomerResource\Pages\EditRecord;
use App\Filament\Resources\CustomerResource\Pages\ListRecords;
use App\Models\Customer;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomerResource extends BusinessResource
{
    protected static ?string $model = Customer::class;

    protected static string $ability = 'crm';

    protected static ?string $module = 'crm';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->required()->maxLength(120),
            TextInput::make('phone')->required()->maxLength(30),
            TextInput::make('email')->email()->maxLength(150),
            Textarea::make('address')])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable(), TextColumn::make('phone')->searchable(), TextColumn::make('email')->searchable()])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListRecords::route('/'), 'create' => CreateRecord::route('/create'), 'edit' => EditRecord::route('/{record}/edit')];
    }
}
