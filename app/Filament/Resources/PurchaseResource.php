<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseResource\Pages;
use App\Models\Purchase;
use App\Support\BusinessTime;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static string | \UnitEnum | null $navigationGroup = 'المخزون';
    protected static ?string $navigationLabel = 'المشتريات';
    protected static ?string $modelLabel = 'أمر شراء';
    protected static ?string $pluralModelLabel = 'المشتريات';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            \Filament\Schemas\Components\Section::make('بيانات أمر الشراء')->schema([
                Forms\Components\Select::make('supplier_id')
                    ->label('المورد')
                    ->relationship('supplier', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('status')
                    ->label('الحالة')
                    ->options([
                        'draft'     => 'مسودة',
                        'ordered'   => 'تم الطلب',
                        'received'  => 'مستلم',
                        'cancelled' => 'ملغي',
                    ])
                    ->default('draft')
                    ->required(),
                Forms\Components\TextInput::make('invoice_number')
                    ->label('رقم الفاتورة')
                    ->maxLength(100),
                Forms\Components\DatePicker::make('invoice_date')
                    ->label('تاريخ الفاتورة'),
                Forms\Components\TextInput::make('subtotal')
                    ->label('المجموع الفرعي')
                    ->numeric()
                    ->prefix('ج.م')
                    ->minValue(0),
                Forms\Components\TextInput::make('tax_amount')
                    ->label('الضريبة')
                    ->numeric()
                    ->prefix('ج.م')
                    ->default(0)
                    ->minValue(0),
                Forms\Components\TextInput::make('discount_amount')
                    ->label('الخصم')
                    ->numeric()
                    ->prefix('ج.م')
                    ->default(0)
                    ->minValue(0),
                Forms\Components\TextInput::make('total')
                    ->label('الإجمالي')
                    ->numeric()
                    ->prefix('ج.م')
                    ->required()
                    ->minValue(0),
                Forms\Components\TextInput::make('paid_amount')
                    ->label('المدفوع')
                    ->numeric()
                    ->prefix('ج.م')
                    ->default(0)
                    ->minValue(0),
                Forms\Components\Select::make('payment_status')
                    ->label('حالة الدفع')
                    ->options([
                        'unpaid'  => 'غير مدفوع',
                        'partial' => 'جزئي',
                        'paid'    => 'مدفوع',
                    ])
                    ->default('unpaid'),
                Forms\Components\Select::make('payment_method')
                    ->label('طريقة الدفع')
                    ->options([
                        'cash'          => 'نقد',
                        'bank_transfer' => 'تحويل بنكي',
                        'credit'        => 'آجل',
                    ])
                    ->nullable(),
                Forms\Components\Textarea::make('notes')
                    ->label('ملاحظات')
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        $businessTimezone = BusinessTime::timezone();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('purchase_number')
                    ->label('رقم الشراء')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('المورد')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state) => match($state) {
                        'draft'     => 'gray',
                        'ordered'   => 'info',
                        'received'  => 'success',
                        'cancelled' => 'danger',
                        default     => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state) => match($state) {
                        'draft'     => 'مسودة',
                        'ordered'   => 'تم الطلب',
                        'received'  => 'مستلم',
                        'cancelled' => 'ملغي',
                        default     => $state,
                    }),
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('رقم الفاتورة')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('الإجمالي')
                    ->money('EGP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('المدفوع')
                    ->money('EGP')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('حالة الدفع')
                    ->badge()
                    ->color(fn (string $state) => match($state) {
                        'paid'    => 'success',
                        'partial' => 'warning',
                        default   => 'danger',
                    })
                    ->formatStateUsing(fn (string $state) => match($state) {
                        'paid'    => 'مدفوع',
                        'partial' => 'جزئي',
                        default   => 'غير مدفوع',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime()
                    ->timezone($businessTimezone)
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'draft'     => 'مسودة',
                        'ordered'   => 'تم الطلب',
                        'received'  => 'مستلم',
                        'cancelled' => 'ملغي',
                    ]),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('حالة الدفع')
                    ->options([
                        'unpaid'  => 'غير مدفوع',
                        'partial' => 'جزئي',
                        'paid'    => 'مدفوع',
                    ]),
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('المورد')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('created_at')
                    ->label('نطاق التاريخ')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('من'),
                        Forms\Components\DatePicker::make('until')->label('إلى'),
                    ])
                    ->query(fn ($query, array $data) => BusinessTime::applyUtcDateRange(
                        $query,
                        $data['from'] ?? null,
                        $data['until'] ?? null,
                    )),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $infolist): Schema
    {
        return $infolist->schema([
            \Filament\Schemas\Components\Section::make('بيانات أمر الشراء')->schema([
                Infolists\Components\TextEntry::make('purchase_number')->label('رقم الشراء'),
                Infolists\Components\TextEntry::make('supplier.name')->label('المورد'),
                Infolists\Components\TextEntry::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn (string $state) => match($state) {
                        'draft' => 'مسودة', 'ordered' => 'تم الطلب',
                        'received' => 'مستلم', 'cancelled' => 'ملغي', default => $state,
                    }),
                Infolists\Components\TextEntry::make('invoice_number')->label('رقم الفاتورة')->placeholder('—'),
                Infolists\Components\TextEntry::make('invoice_date')->label('تاريخ الفاتورة')->date()->placeholder('—'),
                Infolists\Components\TextEntry::make('subtotal')->label('المجموع الفرعي')->money('EGP'),
                Infolists\Components\TextEntry::make('tax_amount')->label('الضريبة')->money('EGP'),
                Infolists\Components\TextEntry::make('discount_amount')->label('الخصم')->money('EGP'),
                Infolists\Components\TextEntry::make('total')->label('الإجمالي')->money('EGP'),
                Infolists\Components\TextEntry::make('paid_amount')->label('المدفوع')->money('EGP'),
                Infolists\Components\TextEntry::make('payment_status')->label('حالة الدفع')->badge()
                    ->formatStateUsing(fn (string $state) => match($state) {
                        'paid' => 'مدفوع', 'partial' => 'جزئي', default => 'غير مدفوع',
                    }),
                Infolists\Components\TextEntry::make('payment_method')->label('طريقة الدفع')->placeholder('—')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'cash' => 'نقد', 'bank_transfer' => 'تحويل بنكي', 'credit' => 'آجل', default => $state ?? '—',
                    }),
            ])->columns(4),
            \Filament\Schemas\Components\Section::make('ملاحظات')->schema([
                Infolists\Components\TextEntry::make('notes')->label('')->placeholder('لا توجد ملاحظات')->columnSpanFull(),
            ])->collapsible()->collapsed(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPurchases::route('/'),
            'create' => Pages\CreatePurchase::route('/create'),
            'edit'   => Pages\EditPurchase::route('/{record}/edit'),
            'view'   => Pages\ViewPurchase::route('/{record}'),
        ];
    }
}
