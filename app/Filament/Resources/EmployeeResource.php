<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages\CreateRecord;
use App\Filament\Resources\EmployeeResource\Pages\EditRecord;
use App\Filament\Resources\EmployeeResource\Pages\ListRecords;
use App\Models\Branch;
use App\Models\Employee;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmployeeResource extends BusinessResource
{
    protected static ?string $model = Employee::class;

    protected static string $ability = 'hr';

    protected static ?string $module = 'hr';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->required()->maxLength(120),
            Select::make('branch_id')->options(fn () => Branch::pluck('name', 'id'))->required(),
            TextInput::make('phone')->required()->maxLength(30),
            TextInput::make('position')->required()->maxLength(100),
            TextInput::make('salary')->label('Monthly salary (poisha)')->numeric()->minValue(0)->required(),
            DatePicker::make('joined_at')->required()])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable(), TextColumn::make('branch.name')->searchable(), TextColumn::make('position')->searchable(), TextColumn::make('salary')->formatStateUsing(fn ($state) => '৳'.number_format($state / 100, 2))])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListRecords::route('/'), 'create' => CreateRecord::route('/create'), 'edit' => EditRecord::route('/{record}/edit')];
    }
}
