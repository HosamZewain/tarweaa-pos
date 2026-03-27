<?php

namespace App\Filament\Resources;

use App\Enums\PaymentTerminalFeeType;
use App\Filament\Resources\PaymentTerminalResource\Pages;
use App\Models\PaymentTerminal;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentTerminalResource extends Resource
{
    protected static ?string $model = PaymentTerminal::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-credit-card';
    protected static string | \UnitEnum | null $navigationGroup = 'الإعدادات';
    protected static ?string $navigationLabel = 'أجهزة الدفع';
    protected static ?string $modelLabel = 'جهاز دفع';
    protected static ?string $pluralModelLabel = 'أجهزة الدفع';
    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            \Filament\Schemas\Components\Section::make('بيانات الجهاز')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('اسم الجهاز')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('bank_name')
                    ->label('اسم البنك')
                    ->maxLength(255),
                Forms\Components\TextInput::make('code')
                    ->label('الكود / المعرّف')
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),
                Forms\Components\Toggle::make('is_active')
                    ->label('نشط')
                    ->default(true),
            ])->columns(2),
            \Filament\Schemas\Components\Section::make('إعداد الرسوم')->schema([
                Forms\Components\Select::make('fee_type')
                    ->label('نوع الرسوم')
                    ->options(collect(PaymentTerminalFeeType::cases())->mapWithKeys(
                        fn (PaymentTerminalFeeType $case) => [$case->value => $case->label()]
                    ))
                    ->default(PaymentTerminalFeeType::Percentage->value)
                    ->required()
                    ->native(false)
                    ->live(),
                Forms\Components\TextInput::make('fee_percentage')
                    ->label('نسبة الرسوم %')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->suffix('%')
                    ->visible(fn (Get $get) => in_array($get('fee_type'), [
                        PaymentTerminalFeeType::Percentage->value,
                        PaymentTerminalFeeType::PercentagePlusFixed->value,
                    ], true)),
                Forms\Components\TextInput::make('fee_fixed_amount')
                    ->label('الرسوم الثابتة')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->suffix('ج.م')
                    ->visible(fn (Get $get) => in_array($get('fee_type'), [
                        PaymentTerminalFeeType::Fixed->value,
                        PaymentTerminalFeeType::PercentagePlusFixed->value,
                    ], true)),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('اسم الجهاز')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('bank_name')->label('البنك')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('code')->label('الكود')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('fee_type')
                    ->label('نوع الرسوم')
                    ->badge()
                    ->formatStateUsing(fn (PaymentTerminalFeeType|string $state) => $state instanceof PaymentTerminalFeeType ? $state->label() : $state),
                Tables\Columns\TextColumn::make('fee_percentage')
                    ->label('النسبة')
                    ->state(fn (PaymentTerminal $record) => number_format((float) $record->fee_percentage, 2) . '%'),
                Tables\Columns\TextColumn::make('fee_fixed_amount')
                    ->label('الثابت')
                    ->money('EGP'),
                Tables\Columns\IconColumn::make('is_active')->label('نشط')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('الحالة'),
                Tables\Filters\SelectFilter::make('fee_type')
                    ->label('نوع الرسوم')
                    ->options(collect(PaymentTerminalFeeType::cases())->mapWithKeys(
                        fn (PaymentTerminalFeeType $case) => [$case->value => $case->label()]
                    )),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('toggleActive')
                    ->label(fn (PaymentTerminal $record) => $record->is_active ? 'إيقاف' : 'تفعيل')
                    ->icon(fn (PaymentTerminal $record) => $record->is_active ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                    ->color(fn (PaymentTerminal $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->visible(fn (PaymentTerminal $record) => auth()->user()?->can('update', $record) ?? false)
                    ->action(function (PaymentTerminal $record): void {
                        $record->update(['is_active' => !$record->is_active]);
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentTerminals::route('/'),
            'create' => Pages\CreatePaymentTerminal::route('/create'),
            'edit' => Pages\EditPaymentTerminal::route('/{record}/edit'),
        ];
    }

    public static function normalizeFeeData(array $data): array
    {
        $type = $data['fee_type'] ?? PaymentTerminalFeeType::Percentage->value;

        if ($type === PaymentTerminalFeeType::Percentage->value) {
            $data['fee_fixed_amount'] = 0;
        }

        if ($type === PaymentTerminalFeeType::Fixed->value) {
            $data['fee_percentage'] = 0;
        }

        $data['fee_percentage'] = (float) ($data['fee_percentage'] ?? 0);
        $data['fee_fixed_amount'] = (float) ($data['fee_fixed_amount'] ?? 0);

        return $data;
    }
}
