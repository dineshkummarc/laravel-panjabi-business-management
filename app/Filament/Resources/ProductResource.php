<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages\CreateRecord;
use App\Filament\Resources\ProductResource\Pages\EditRecord;
use App\Filament\Resources\ProductResource\Pages\ListRecords;
use App\Models\Category;
use App\Models\Product;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductResource extends BusinessResource
{
    protected static ?string $model = Product::class;

    protected static string $ability = 'catalog';

    protected static ?string $module = 'inventory';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->required()->maxLength(150),
            TextInput::make('slug')->required()->maxLength(150),
            Select::make('category_id')->options(fn () => Category::pluck('name', 'id'))->searchable(),
            Textarea::make('description')->rows(4),
            TextInput::make('fabric')->maxLength(100),
            TextInput::make('fit')->default('Regular')->required(),
            Select::make('kind')->options(['finished' => 'Finished product', 'material' => 'Raw material'])->default('finished')->required(),
            Select::make('image')->options(['' => 'No image', '/images/panjabi-sage.svg' => 'Sage illustration', '/images/panjabi-ivory.svg' => 'Ivory illustration', '/images/panjabi-navy.svg' => 'Navy illustration', '/images/panjabi-maroon.svg' => 'Maroon illustration', '/images/panjabi-olive.svg' => 'Olive illustration', '/images/panjabi-black.svg' => 'Black illustration']),
            FileUpload::make('photo_path')->label('Product photograph')->disk('public')->directory('products')->visibility('public')->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(4096), Toggle::make('active')->default(true),
            Toggle::make('featured')])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable(), TextColumn::make('category.name')->searchable(), TextColumn::make('fabric')->searchable(), TextColumn::make('kind')->searchable(), TextColumn::make('active')->searchable()])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListRecords::route('/'), 'create' => CreateRecord::route('/create'), 'edit' => EditRecord::route('/{record}/edit')];
    }
}
