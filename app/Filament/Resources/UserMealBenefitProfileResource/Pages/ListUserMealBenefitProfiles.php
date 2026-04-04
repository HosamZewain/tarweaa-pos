<?php

namespace App\Filament\Resources\UserMealBenefitProfileResource\Pages;

use App\Enums\UserMealBenefitFreeMealType;
use App\Enums\UserMealBenefitPeriodType;
use App\Filament\Resources\UserMealBenefitProfileResource;
use App\Models\MenuItem;
use App\Models\User;
use App\Models\UserMealBenefitProfile;
use App\Services\AdminActivityLogService;
use App\Services\UserMealBenefitProfileService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;

class ListUserMealBenefitProfiles extends ListRecords
{
    protected static string $resource = UserMealBenefitProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('إضافة ملف مزايا'),
            Actions\Action::make('bulkAssign')
                ->label('إسناد جماعي')
                ->icon('heroicon-o-users')
                ->color('info')
                ->modalHeading('إسناد مزايا لعدة مستخدمين')
                ->modalDescription('اختر عدة مستخدمين ثم طبّق نفس نوع المزية عليهم دفعة واحدة. إذا كان للمستخدم ملف سابق فسيتم تحديثه.')
                ->schema([
                    Forms\Components\Select::make('user_ids')
                        ->label('المستخدمون / الموظفون')
                        ->options(fn (): array => User::query()
                            ->where('is_active', true)
                            ->with('roles')
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(function (User $user): array {
                                $roles = $user->roles->pluck('display_name')->filter()->implode('، ');
                                $label = $roles !== '' ? "{$user->name} ({$roles})" : $user->name;

                                return [$user->id => $label];
                            })
                            ->all())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->required(),
                    Forms\Components\Select::make('benefit_mode')
                        ->label('نوع المزية')
                        ->required()
                        ->default(UserMealBenefitProfile::BENEFIT_MODE_NONE)
                        ->options([
                            UserMealBenefitProfile::BENEFIT_MODE_NONE => 'بدون مزايا',
                            UserMealBenefitProfile::BENEFIT_MODE_OWNER_CHARGE => 'تحميل مالك / إدارة',
                            UserMealBenefitProfile::BENEFIT_MODE_MONTHLY_ALLOWANCE => 'بدل الموظف',
                            UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL => 'وجبة مجانية للموظف',
                        ])
                        ->native(false)
                        ->live(),
                    Forms\Components\Select::make('benefit_period_type')
                        ->label('فترة الحد')
                        ->options(collect(UserMealBenefitPeriodType::cases())->mapWithKeys(fn (UserMealBenefitPeriodType $type) => [
                            $type->value => $type->label(),
                        ])->all())
                        ->formatStateUsing(fn ($state) => $state ?: UserMealBenefitPeriodType::Monthly->value)
                        ->default(UserMealBenefitPeriodType::Monthly->value)
                        ->required(fn (Get $get): bool => in_array($get('benefit_mode'), [
                            UserMealBenefitProfile::BENEFIT_MODE_MONTHLY_ALLOWANCE,
                            UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL,
                        ], true))
                        ->visible(fn (Get $get): bool => in_array($get('benefit_mode'), [
                            UserMealBenefitProfile::BENEFIT_MODE_MONTHLY_ALLOWANCE,
                            UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL,
                        ], true))
                        ->native(false),
                    Forms\Components\Toggle::make('is_active')
                        ->label('نشط')
                        ->default(true),
                    Forms\Components\TextInput::make('monthly_allowance_amount')
                        ->label('قيمة البدل')
                        ->numeric()
                        ->minValue(0)
                        ->step('0.01')
                        ->prefix('ج.م')
                        ->required(fn (Get $get): bool => $get('benefit_mode') === UserMealBenefitProfile::BENEFIT_MODE_MONTHLY_ALLOWANCE)
                        ->visible(fn (Get $get): bool => $get('benefit_mode') === UserMealBenefitProfile::BENEFIT_MODE_MONTHLY_ALLOWANCE),
                    Forms\Components\Select::make('free_meal_type')
                        ->label('نوع الحد')
                        ->options(collect(UserMealBenefitFreeMealType::cases())->mapWithKeys(fn (UserMealBenefitFreeMealType $type) => [
                            $type->value => $type->label(),
                        ])->all())
                        ->required(fn (Get $get): bool => $get('benefit_mode') === UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL)
                        ->native(false)
                        ->live()
                        ->visible(fn (Get $get): bool => $get('benefit_mode') === UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL),
                    Forms\Components\TextInput::make('free_meal_monthly_count')
                        ->label('عدد الوجبات لكل فترة')
                        ->numeric()
                        ->minValue(0)
                        ->step(1)
                        ->required(fn (Get $get): bool => $get('benefit_mode') === UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL && $get('free_meal_type') === UserMealBenefitFreeMealType::Count->value)
                        ->visible(fn (Get $get): bool => $get('benefit_mode') === UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL && $get('free_meal_type') === UserMealBenefitFreeMealType::Count->value),
                    Forms\Components\TextInput::make('free_meal_monthly_amount')
                        ->label('حد المبلغ لكل فترة')
                        ->numeric()
                        ->minValue(0)
                        ->step('0.01')
                        ->prefix('ج.م')
                        ->required(fn (Get $get): bool => $get('benefit_mode') === UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL && $get('free_meal_type') === UserMealBenefitFreeMealType::Amount->value)
                        ->visible(fn (Get $get): bool => $get('benefit_mode') === UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL && $get('free_meal_type') === UserMealBenefitFreeMealType::Amount->value),
                    Forms\Components\Select::make('allowedMenuItems')
                        ->label('الأصناف المسموح بها')
                        ->options(fn (): array => MenuItem::query()
                            ->with('category')
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (MenuItem $record): array => [
                                $record->id => $record->category?->name
                                    ? "{$record->name} - {$record->category->name}"
                                    : $record->name,
                            ])
                            ->all())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->visible(fn (Get $get): bool => $get('benefit_mode') === UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL),
                    Forms\Components\Textarea::make('notes')
                        ->label('ملاحظات')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $count = app(UserMealBenefitProfileService::class)->upsertForUsers(
                        $data['user_ids'] ?? [],
                        $data,
                        auth()->id(),
                    );

                    app(AdminActivityLogService::class)->logAction(
                        action: 'bulk_assigned',
                        description: 'تم تنفيذ إسناد جماعي لملفات مزايا الوجبات.',
                        newValues: [
                            'user_ids' => $data['user_ids'] ?? [],
                            'benefit_mode' => $data['benefit_mode'] ?? null,
                            'updated_profiles_count' => $count,
                        ],
                        module: 'user_meal_benefit_profiles',
                        subjectLabel: 'إسناد جماعي لملفات المزايا',
                    );

                    Notification::make()
                        ->title('تم حفظ الإسناد الجماعي')
                        ->body("تم تحديث {$count} ملف مزايا بنجاح.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
