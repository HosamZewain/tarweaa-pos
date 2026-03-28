<?php

namespace App\Filament\Resources;

use App\Enums\UserMealBenefitFreeMealType;
use App\Filament\Pages\MealBenefitsReport;
use App\Filament\Resources\UserMealBenefitProfileResource\Pages;
use App\Models\MenuItem;
use App\Models\User;
use App\Models\UserMealBenefitProfile;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class UserMealBenefitProfileResource extends Resource
{
    protected static ?string $model = UserMealBenefitProfile::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-identification';
    protected static string | \UnitEnum | null $navigationGroup = 'الإعدادات';
    protected static ?string $navigationLabel = 'مزايا الوجبات';
    protected static ?string $modelLabel = 'ملف مزايا';
    protected static ?string $pluralModelLabel = 'ملفات مزايا الوجبات';
    protected static ?int $navigationSort = 21;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user.roles', 'allowedMenuItems'])
            ->withCount('allowedMenuItems');
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make('الملف الأساسي')->schema([
                Forms\Components\Select::make('user_id')
                    ->label('المستخدم / الموظف')
                    ->relationship(
                        'user',
                        'name',
                        modifyQueryUsing: fn (Builder $query) => $query
                            ->where('is_active', true)
                            ->with('roles')
                            ->orderBy('name'),
                    )
                    ->getOptionLabelFromRecordUsing(function (User $record): string {
                        $roles = $record->roles->pluck('display_name')->filter()->implode('، ');

                        return $roles !== ''
                            ? "{$record->name} ({$roles})"
                            : $record->name;
                    })
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required()
                    ->rule(fn (?UserMealBenefitProfile $record) => Rule::unique('user_meal_benefit_profiles', 'user_id')->ignore($record?->id)),
                Forms\Components\Select::make('benefit_mode')
                    ->label('نوع المزية')
                    ->required()
                    ->default(UserMealBenefitProfile::BENEFIT_MODE_NONE)
                    ->options([
                        UserMealBenefitProfile::BENEFIT_MODE_NONE => 'بدون مزايا',
                        UserMealBenefitProfile::BENEFIT_MODE_OWNER_CHARGE => 'تحميل مالك / إدارة',
                        UserMealBenefitProfile::BENEFIT_MODE_MONTHLY_ALLOWANCE => 'بدل شهري للموظف',
                        UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL => 'وجبة مجانية للموظف',
                    ])
                    ->native(false)
                    ->live()
                    ->helperText('اختر نوع التفعيل الأساسي لهذا الملف. لكل مستخدم ملف واحد نشط للتشغيل الحالي.'),
                Forms\Components\Toggle::make('is_active')
                    ->label('نشط')
                    ->default(true),
                Forms\Components\Textarea::make('notes')
                    ->label('ملاحظات')
                    ->rows(3)
                    ->columnSpanFull(),
            ])->columns(3),

            Section::make('تحميل مالك / إدارة')->schema([
                Forms\Components\Placeholder::make('owner_charge_help')
                    ->label('')
                    ->content('فعّل هذا النوع فقط للمستخدمين الذين يمكن تحميل الطلبات عليهم بدون تحصيل فوري.'),
            ])->visible(fn (Get $get): bool => $get('benefit_mode') === UserMealBenefitProfile::BENEFIT_MODE_OWNER_CHARGE),

            Section::make('البدل الشهري')->schema([
                Forms\Components\TextInput::make('monthly_allowance_amount')
                    ->label('قيمة البدل الشهري')
                    ->numeric()
                    ->minValue(0)
                    ->step('0.01')
                    ->prefix('ج.م')
                    ->required(fn (Get $get): bool => $get('benefit_mode') === UserMealBenefitProfile::BENEFIT_MODE_MONTHLY_ALLOWANCE)
                    ->helperText('يعاد احتساب الرصيد المتبقي شهريًا من خلال دفتر الحركات.'),
            ])->visible(fn (Get $get): bool => $get('benefit_mode') === UserMealBenefitProfile::BENEFIT_MODE_MONTHLY_ALLOWANCE),

            Section::make('الوجبة المجانية')->schema([
                Forms\Components\Select::make('free_meal_type')
                    ->label('نوع الحد')
                    ->options(collect(UserMealBenefitFreeMealType::cases())->mapWithKeys(fn (UserMealBenefitFreeMealType $type) => [
                        $type->value => $type->label(),
                    ])->all())
                    ->required(fn (Get $get): bool => $get('benefit_mode') === UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL)
                    ->native(false)
                    ->live(),
                Forms\Components\TextInput::make('free_meal_monthly_count')
                    ->label('عدد الوجبات الشهرية')
                    ->numeric()
                    ->minValue(0)
                    ->step(1)
                    ->required(fn (Get $get): bool => $get('benefit_mode') === UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL && $get('free_meal_type') === UserMealBenefitFreeMealType::Count->value)
                    ->visible(fn (Get $get): bool => $get('benefit_mode') === UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL && $get('free_meal_type') === UserMealBenefitFreeMealType::Count->value),
                Forms\Components\TextInput::make('free_meal_monthly_amount')
                    ->label('الحد الشهري بالمبلغ')
                    ->numeric()
                    ->minValue(0)
                    ->step('0.01')
                    ->prefix('ج.م')
                    ->required(fn (Get $get): bool => $get('benefit_mode') === UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL && $get('free_meal_type') === UserMealBenefitFreeMealType::Amount->value)
                    ->visible(fn (Get $get): bool => $get('benefit_mode') === UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL && $get('free_meal_type') === UserMealBenefitFreeMealType::Amount->value),
                Forms\Components\Select::make('allowedMenuItems')
                    ->label('الأصناف المسموح بها')
                    ->relationship(
                        'allowedMenuItems',
                        'name',
                        modifyQueryUsing: fn (Builder $query) => $query
                            ->with('category')
                            ->where('is_active', true)
                            ->orderBy('name'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (MenuItem $record): string => $record->category?->name
                        ? "{$record->name} - {$record->category->name}"
                        : $record->name)
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->helperText('تُطبق مزية الوجبة المجانية على هذه الأصناف فقط.')
                    ->visible(fn (Get $get): bool => $get('benefit_mode') === UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL)
                    ->columnSpanFull(),
            ])->visible(fn (Get $get): bool => $get('benefit_mode') === UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL)
                ->columns(2),

            Section::make('ملاحظة تشغيلية')->schema([
                Forms\Components\Placeholder::make('effective_dates_note')
                    ->label('سريان التهيئة')
                    ->content('تُطبق الإعدادات فورًا بعد الحفظ. لا توجد نافذة تاريخية منفصلة لهذه الميزة حاليًا.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('المستخدم')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.roles.display_name')
                    ->label('الدور')
                    ->badge(),
                Tables\Columns\TextColumn::make('benefit_mode')
                    ->label('نوع المزية')
                    ->state(fn (UserMealBenefitProfile $record): string => $record->benefitModeLabel())
                    ->badge()
                    ->color(fn (UserMealBenefitProfile $record): string => match ($record->benefitMode()) {
                        UserMealBenefitProfile::BENEFIT_MODE_OWNER_CHARGE => 'warning',
                        UserMealBenefitProfile::BENEFIT_MODE_MONTHLY_ALLOWANCE => 'info',
                        UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL => 'success',
                        'mixed' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('monthly_allowance_amount')
                    ->label('البدل الشهري')
                    ->money('EGP')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('free_meal_limit')
                    ->label('حد الوجبة المجانية')
                    ->state(fn (UserMealBenefitProfile $record): string => $record->freeMealLimitLabel()),
                Tables\Columns\TextColumn::make('allowed_menu_items_count')
                    ->label('الأصناف المسموحة')
                    ->badge()
                    ->color('success'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('الحالة'),
                Tables\Filters\SelectFilter::make('mode')
                    ->label('نوع المزية')
                    ->options([
                        UserMealBenefitProfile::BENEFIT_MODE_NONE => 'بدون مزايا',
                        UserMealBenefitProfile::BENEFIT_MODE_OWNER_CHARGE => 'تحميل مالك / إدارة',
                        UserMealBenefitProfile::BENEFIT_MODE_MONTHLY_ALLOWANCE => 'بدل شهري',
                        UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL => 'وجبة مجانية',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            UserMealBenefitProfile::BENEFIT_MODE_OWNER_CHARGE => $query->where('can_receive_owner_charge_orders', true),
                            UserMealBenefitProfile::BENEFIT_MODE_MONTHLY_ALLOWANCE => $query->where('monthly_allowance_enabled', true),
                            UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL => $query->where('free_meal_enabled', true),
                            UserMealBenefitProfile::BENEFIT_MODE_NONE => $query
                                ->where('can_receive_owner_charge_orders', false)
                                ->where('monthly_allowance_enabled', false)
                                ->where('free_meal_enabled', false),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\Action::make('statement')
                    ->label('كشف الاستخدام')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('info')
                    ->url(fn (UserMealBenefitProfile $record): string => MealBenefitsReport::getUrl(['user_id' => $record->user_id])),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserMealBenefitProfiles::route('/'),
            'create' => Pages\CreateUserMealBenefitProfile::route('/create'),
            'edit' => Pages\EditUserMealBenefitProfile::route('/{record}/edit'),
        ];
    }
}
