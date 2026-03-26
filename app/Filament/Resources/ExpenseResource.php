<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use App\Services\ExpenseService;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-receipt-percent';
    protected static string | \UnitEnum | null $navigationGroup = 'المالية';
    protected static ?string $navigationLabel = 'المصروفات';
    protected static ?string $modelLabel = 'مصروف';
    protected static ?string $pluralModelLabel = 'المصروفات';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            \Filament\Schemas\Components\Section::make('بيانات المصروف')->schema([
                Forms\Components\Select::make('category_id')->label('التصنيف')->relationship('category', 'name')->required()->searchable()->preload(),
                Forms\Components\TextInput::make('amount')->label('المبلغ')->numeric()->required()->prefix('ج.م'),
                Forms\Components\Textarea::make('description')->label('الوصف')->required()->maxLength(500),
                Forms\Components\Select::make('payment_method')->label('طريقة الدفع')->options([
                    'cash' => 'نقد', 'bank_transfer' => 'تحويل بنكي', 'credit_card' => 'بطاقة ائتمان',
                ])->default('cash'),
                Forms\Components\TextInput::make('receipt_number')->label('رقم الإيصال')->maxLength(100),
                Forms\Components\DatePicker::make('expense_date')->label('التاريخ')->default(now())->required(),
                Forms\Components\Select::make('shift_id')->label('الوردية')->relationship('shift', 'shift_number')->searchable()->preload()->nullable(),
                Forms\Components\Textarea::make('notes')->label('ملاحظات')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('expense_number')->label('رقم المصروف')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category.name')->label('التصنيف')->sortable(),
                Tables\Columns\TextColumn::make('description')->label('الوصف')->limit(40),
                Tables\Columns\TextColumn::make('amount')->label('المبلغ')->money('EGP')->sortable(),
                Tables\Columns\TextColumn::make('payment_method')->label('طريقة الدفع'),
                Tables\Columns\TextColumn::make('expense_date')->label('التاريخ')->date()->sortable(),
                Tables\Columns\TextColumn::make('approver.name')->label('الموافقة بواسطة')->placeholder('في الانتظار')
                    ->color(fn ($state) => $state ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('shift.shift_number')->label('الوردية')->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')->label('التصنيف')->relationship('category', 'name'),
                Tables\Filters\Filter::make('unapproved')->label('بانتظار الموافقة')
                    ->query(fn ($query) => $query->whereNull('approved_by')),
                Tables\Filters\Filter::make('expense_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('من'),
                        Forms\Components\DatePicker::make('until')->label('إلى'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('expense_date', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('expense_date', '<=', $date));
                    }),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\Action::make('approve')
                    ->label('موافقة')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Expense $record) => !$record->isApproved() && auth()->user()?->hasPermission('expenses.approve'))
                    ->action(function (Expense $record) {
                        abort_unless(auth()->user()?->hasPermission('expenses.approve'), 403);
                        app(ExpenseService::class)->approve($record, auth()->id());
                        Notification::make()->title('تمت الموافقة على المصروف')->success()->send();
                    }),
            ])
            ->bulkActions([\Filament\Actions\BulkActionGroup::make([\Filament\Actions\DeleteBulkAction::make()])])
            ->defaultSort('expense_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit'   => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
