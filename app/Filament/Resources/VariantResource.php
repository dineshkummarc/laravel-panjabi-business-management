<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VariantResource\Pages\CreateRecord;
use App\Filament\Resources\VariantResource\Pages\EditRecord;
use App\Filament\Resources\VariantResource\Pages\ListRecords;
use App\Models\Product;
use App\Models\Variant;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VariantResource extends BusinessResource
{
    protected static ?string $model = Variant::class;

    protected static string $ability = 'catalog';

    protected static ?string $module = 'inventory';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Select::make('product_id')->options(fn () => Product::pluck('name', 'id'))->searchable()->required(),
            TextInput::make('sku')->required()->maxLength(100),
            TextInput::make('barcode')->maxLength(100),
            TextInput::make('size')->required()->maxLength(50),
            TextInput::make('color')->required()->maxLength(50),
            TextInput::make('price')->label('Retail price (poisha)')->numeric()->minValue(0)->required(),
            TextInput::make('wholesale_price')->label('Wholesale price (poisha)')->numeric()->minValue(0)->default(0)->required(),
            TextInput::make('cost')->label('Standard unit cost (poisha)')->numeric()->minValue(0)->required(),
            TextInput::make('reorder_level')->numeric()->minValue(0)->default(5)->required()])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('sku')->searchable(), TextColumn::make('product.name')->searchable(), TextColumn::make('size')->searchable(), TextColumn::make('color')->searchable(), TextColumn::make('price')->formatStateUsing(fn ($state) => '৳'.number_format($state / 100, 2)), TextColumn::make('cost')->formatStateUsing(fn ($state) => '৳'.number_format($state / 100, 2))])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListRecords::route('/'), 'create' => CreateRecord::route('/create'), 'edit' => EditRecord::route('/{record}/edit')];
    }
}
