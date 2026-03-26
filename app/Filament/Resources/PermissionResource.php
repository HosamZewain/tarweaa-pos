<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PermissionResource\Pages;
use App\Models\Permission;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-key';
    protected static string | \UnitEnum | null $navigationGroup = 'الإدارة';
    protected static ?string $navigationLabel = 'الصلاحيات';
    protected static ?string $modelLabel = 'صلاحية';
    protected static ?string $pluralModelLabel = 'الصلاحيات';
    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('group')
                    ->label('المجموعة')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                Tables\Columns\TextColumn::make('display_name')
                    ->label('الاسم المعروض')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('المعرّف البرمجي')
                    ->searchable()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('roles_count')
                    ->counts('roles')
                    ->label('الأدوار')
                    ->badge()
                    ->color('info')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('group')
                    ->label('المجموعة')
                    ->options(
                        Permission::distinct()->orderBy('group')->pluck('group', 'group')
                    ),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->defaultSort('group')
            ->groups([
                Tables\Grouping\Group::make('group')->label('المجموعة'),
            ])
            ->defaultGroup('group');
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('المعرّف البرمجي')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(50)
                ->helperText('مثل users.viewAny أو roles.update'),
            Forms\Components\TextInput::make('display_name')
                ->label('الاسم المعروض')
                ->required()
                ->maxLength(100),
            Forms\Components\TextInput::make('group')
                ->label('المجموعة')
                ->required()
                ->maxLength(50)
                ->placeholder('مثال: التقارير، القائمة، الإعدادات'),
        ])->columns(2);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPermissions::route('/'),
            'create' => Pages\CreatePermission::route('/create'),
            'edit'   => Pages\EditPermission::route('/{record}/edit'),
        ];
    }
}
