<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchResource\Pages\CreateRecord;
use App\Filament\Resources\BranchResource\Pages\EditRecord;
use App\Filament\Resources\BranchResource\Pages\ListRecords;
use App\Models\Branch;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BranchResource extends BusinessResource
{
    protected static ?string $model = Branch::class;

    protected static string $ability = 'settings';

    protected static ?string $module = null;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->required()->maxLength(120),
            TextInput::make('code')->required()->maxLength(20),
            Textarea::make('address'),
            TextInput::make('phone')->maxLength(30),
            Toggle::make('active')->default(true)])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable(), TextColumn::make('code')->searchable(), TextColumn::make('phone')->searchable(), TextColumn::make('active')->searchable()])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListRecords::route('/'), 'create' => CreateRecord::route('/create'), 'edit' => EditRecord::route('/{record}/edit')];
    }
}
