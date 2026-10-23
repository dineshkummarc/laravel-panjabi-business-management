<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages\CreateRecord;
use App\Filament\Resources\CategoryResource\Pages\EditRecord;
use App\Filament\Resources\CategoryResource\Pages\ListRecords;
use App\Models\Category;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoryResource extends BusinessResource
{
    protected static ?string $model = Category::class;

    protected static string $ability = 'catalog';

    protected static ?string $module = 'inventory';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->required()->maxLength(100),
            TextInput::make('slug')->required()->maxLength(100)])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable(), TextColumn::make('slug')->searchable()])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListRecords::route('/'), 'create' => CreateRecord::route('/create'), 'edit' => EditRecord::route('/{record}/edit')];
    }
}
