<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-truck';
    protected static string | \UnitEnum | null $navigationGroup = 'المخزون';
    protected static ?string $navigationLabel = 'الموردون';
    protected static ?string $modelLabel = 'مورد';
    protected static ?string $pluralModelLabel = 'الموردون';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            \Filament\Schemas\Components\Section::make('بيانات المورد')->schema([
                Forms\Components\TextInput::make('name')->label('الاسم')->required()->maxLength(255),
                Forms\Components\TextInput::make('contact_person')->label('جهة الاتصال')->maxLength(255),
                Forms\Components\TextInput::make('phone')->label('الهاتف')->tel()->maxLength(20),
                Forms\Components\TextInput::make('email')->label('البريد')->email()->maxLength(255),
                Forms\Components\Textarea::make('address')->label('العنوان')->maxLength(500),
                Forms\Components\TextInput::make('tax_number')->label('الرقم الضريبي')->maxLength(50),
                Forms\Components\TextInput::make('payment_terms')->label('شروط الدفع')->maxLength(100),
                Forms\Components\Toggle::make('is_active')->label('نشط')->default(true),
                Forms\Components\Textarea::make('notes')->label('ملاحظات')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('contact_person')->label('جهة الاتصال')->searchable(),
                Tables\Columns\TextColumn::make('phone')->label('الهاتف'),
                Tables\Columns\TextColumn::make('email')->label('البريد'),
                Tables\Columns\TextColumn::make('purchases_count')->counts('purchases')->label('المشتريات'),
                Tables\Columns\IconColumn::make('is_active')->label('نشط')->boolean(),
            ])
            ->filters([Tables\Filters\TernaryFilter::make('is_active')->label('الحالة')])
            ->actions([\Filament\Actions\EditAction::make()])
            ->bulkActions([\Filament\Actions\BulkActionGroup::make([\Filament\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit'   => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
