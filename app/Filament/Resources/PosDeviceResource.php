<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PosDeviceResource\Pages;
use App\Models\PosDevice;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PosDeviceResource extends Resource
{
    protected static ?string $model = PosDevice::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-device-tablet';
    protected static string | \UnitEnum | null $navigationGroup = 'الإعدادات';
    protected static ?string $navigationLabel = 'أجهزة POS';
    protected static ?string $modelLabel = 'جهاز POS';
    protected static ?string $pluralModelLabel = 'أجهزة POS';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('الاسم')->required()->maxLength(255),
            Forms\Components\TextInput::make('identifier')->label('المعرّف')->required()->maxLength(100)->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('location')->label('الموقع')->maxLength(255),
            Forms\Components\Toggle::make('is_active')->label('نشط')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('identifier')->label('المعرّف')->searchable(),
                Tables\Columns\TextColumn::make('location')->label('الموقع')->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')->label('نشط')->boolean(),
                Tables\Columns\TextColumn::make('last_seen_at')->label('آخر اتصال')->dateTime()->placeholder('—'),
            ])
            ->filters([Tables\Filters\TernaryFilter::make('is_active')->label('الحالة')])
            ->actions([\Filament\Actions\EditAction::make()])
            ->bulkActions([\Filament\Actions\BulkActionGroup::make([\Filament\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPosDevices::route('/'),
            'create' => Pages\CreatePosDevice::route('/create'),
            'edit'   => Pages\EditPosDevice::route('/{record}/edit'),
        ];
    }
}
