<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampaignResource\Pages\CreateRecord;
use App\Filament\Resources\CampaignResource\Pages\EditRecord;
use App\Filament\Resources\CampaignResource\Pages\ListRecords;
use App\Models\Campaign;
use App\Models\Category;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CampaignResource extends BusinessResource
{
    protected static ?string $model = Campaign::class;

    protected static string $ability = 'settings';

    protected static ?string $module = 'storefront';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->required()->maxLength(150), TextInput::make('slug')->required()->maxLength(150), TextInput::make('headline')->required()->maxLength(150), Textarea::make('description')->required()->maxLength(5000), Select::make('category_id')->options(fn () => Category::pluck('name', 'id')), DateTimePicker::make('starts_at'), DateTimePicker::make('ends_at')->after('starts_at'), Toggle::make('active')])->columns(2);
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
