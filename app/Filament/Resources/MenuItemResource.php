<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuItemResource\Pages;
use App\Models\MenuItem;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;

class MenuItemResource extends Resource
{
    protected static ?string $model = MenuItem::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cake';
    protected static string | \UnitEnum | null $navigationGroup = 'القائمة';
    protected static ?string $navigationLabel = 'الأصناف';
    protected static ?string $modelLabel = 'صنف';
    protected static ?string $pluralModelLabel = 'الأصناف';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            \Filament\Schemas\Components\Section::make('بيانات الصنف')->schema([
                Forms\Components\TextInput::make('name')->label('الاسم')->required()->maxLength(255),
                Forms\Components\Select::make('category_id')->label('الفئة')->relationship('category', 'name')->required()->searchable()->preload(),
                Forms\Components\Textarea::make('description')->label('الوصف')->maxLength(500),
                Forms\Components\TextInput::make('sku')->label('SKU')->maxLength(50),
                Forms\Components\Select::make('type')->label('النوع')->options(['simple' => 'بسيط', 'variable' => 'متعدد'])->default('simple')->required(),
                Forms\Components\TextInput::make('base_price')->label('السعر الأساسي')->numeric()->prefix('ج.م')->required(),
                Forms\Components\TextInput::make('cost_price')->label('سعر التكلفة')->numeric()->prefix('ج.م'),
                Forms\Components\TextInput::make('preparation_time')->label('وقت التحضير (دقائق)')->numeric(),
                Forms\Components\FileUpload::make('image')->label('الصورة')->image()->directory('menu-items')->nullable(),
                Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),
            ])->columns(2),
            \Filament\Schemas\Components\Section::make('الحالة')->schema([
                Forms\Components\Toggle::make('is_active')->label('نشط')->default(true),
                Forms\Components\Toggle::make('is_available')->label('متاح')->default(true),
                Forms\Components\Toggle::make('track_inventory')->label('تتبع المخزون')->default(false),
            ])->columns(3),
            \Filament\Schemas\Components\Section::make('الخيارات (Variants)')
                ->schema([
                    Forms\Components\Repeater::make('variants')
                        ->relationship('variants')
                        ->schema([
                            Forms\Components\TextInput::make('name')->label('الاسم (مثلاً: صغير)')->required(),
                            Forms\Components\TextInput::make('price')->label('السعر')->numeric()->required()->prefix('ج.م'),
                            Forms\Components\TextInput::make('sku')->label('SKU'),
                            Forms\Components\Toggle::make('is_available')->label('متاح')->default(true),
                        ])
                        ->columns(4)
                        ->visible(fn (Get $get) => $get('type') === 'variable'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')->label('الصورة')->circular(),
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category.name')->label('الفئة')->sortable(),
                Tables\Columns\TextColumn::make('type')->label('النوع')->badge(),
                Tables\Columns\TextColumn::make('base_price')
                    ->label('السعر')
                    ->getStateUsing(fn (MenuItem $record) => 
                        $record->type === 'variable' 
                            ? ($record->variants->isEmpty() ? '0.00' : $record->variants->min('price') . ' - ' . $record->variants->max('price'))
                            : $record->base_price
                    )
                    ->money('EGP')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_available')->label('متاح')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->label('نشط')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')->label('الفئة')->relationship('category', 'name'),
                Tables\Filters\TernaryFilter::make('is_active')->label('نشط'),
                Tables\Filters\TernaryFilter::make('is_available')->label('متاح'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\Action::make('toggleAvailability')
                    ->label(fn (MenuItem $record) => $record->is_available ? 'غير متاح' : 'متاح')
                    ->icon('heroicon-o-eye')
                    ->color(fn (MenuItem $record) => $record->is_available ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (MenuItem $record) => $record->toggleAvailability()),
            ])
            ->bulkActions([\Filament\Actions\BulkActionGroup::make([\Filament\Actions\DeleteBulkAction::make()])])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMenuItems::route('/'),
            'create' => Pages\CreateMenuItem::route('/create'),
            'edit'   => Pages\EditMenuItem::route('/{record}/edit'),
        ];
    }
}
