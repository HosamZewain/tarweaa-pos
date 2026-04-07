<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\RelationManagers\EmployeeAdvancesRelationManager;
use App\Filament\Resources\EmployeeResource\RelationManagers\EmployeePenaltiesRelationManager;
use App\Filament\Resources\EmployeeResource\RelationManagers\EmployeeSalariesRelationManager;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use App\Models\User;
use App\Support\BusinessTime;
use App\Support\HrFeature;
use App\Services\AdminActivityLogService;
use App\Services\EmployeeManagementService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-group';
    protected static string | \UnitEnum | null $navigationGroup = 'HR';
    protected static ?string $navigationLabel = 'الموظفون';
    protected static ?string $modelLabel = 'موظف';
    protected static ?string $pluralModelLabel = 'الموظفون';
    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $form): Schema
    {
        $employeeService = app(EmployeeManagementService::class);
        $summarySchema = [
            Forms\Components\Placeholder::make('current_salary_summary')
                ->label('الراتب الحالي')
                ->content(function (?Employee $record): string {
                    $salary = $record?->currentEmployeeSalaryRecord();

                    return $salary ? number_format((float) $salary->amount, 2) . ' ج.م' : 'غير محدد';
                }),
            Forms\Components\Placeholder::make('salary_effective_summary')
                ->label('سريان الراتب الحالي')
                ->content(function (?Employee $record): string {
                    $salary = $record?->currentEmployeeSalaryRecord();

                    if (!$salary) {
                        return 'لا يوجد راتب ساري';
                    }

                    $from = $salary->effective_from?->format('Y-m-d') ?? '—';
                    $to = $salary->effective_to?->format('Y-m-d') ?? 'مستمر';

                    return "{$from} → {$to}";
                }),
            Forms\Components\Placeholder::make('active_penalties_count_summary')
                ->label('عدد الجزاءات النشطة')
                ->content(fn (?Employee $record): string => (string) ($record?->activeEmployeePenaltiesCount() ?? 0)),
            Forms\Components\Placeholder::make('active_penalties_total_summary')
                ->label('إجمالي الجزاءات النشطة')
                ->content(fn (?Employee $record): string => number_format((float) ($record?->activeEmployeePenaltiesTotal() ?? 0), 2) . ' ج.م'),
        ];

        if (HrFeature::hasAdvanceTables()) {
            $summarySchema[] = Forms\Components\Placeholder::make('active_advances_count_summary')
                ->label('عدد السلف النشطة')
                ->content(fn (?Employee $record): string => (string) ($record?->activeEmployeeAdvancesCount() ?? 0));

            $summarySchema[] = Forms\Components\Placeholder::make('active_advances_total_summary')
                ->label('إجمالي السلف النشطة')
                ->content(fn (?Employee $record): string => number_format((float) ($record?->activeEmployeeAdvancesTotal() ?? 0), 2) . ' ج.م');
        }

        return $form->schema([
            \Filament\Schemas\Components\Section::make('ملخص HR')
                ->schema($summarySchema)
                ->columns(3)
                ->visible(fn (string $operation): bool => $operation === 'edit'),

            \Filament\Schemas\Components\Section::make('بيانات الموظف')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('الاسم')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('username')
                    ->label('اسم المستخدم')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('البريد الإلكتروني')
                    ->email()
                    ->helperText('اختياري')
                    ->placeholder('اختياري')
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->label('الهاتف')
                    ->tel()
                    ->maxLength(20),
                Forms\Components\TextInput::make('password')
                    ->label('كلمة المرور')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->maxLength(255),
                Forms\Components\TextInput::make('pin')
                    ->label('رمز PIN')
                    ->password()
                    ->helperText('اختياري. من 4 إلى 6 أرقام عند الحاجة للدخول من شاشات التشغيل، ويجب أن يكون فريدًا بين الحسابات النشطة.')
                    ->minLength(4)
                    ->maxLength(6)
                    ->rule('regex:/^[0-9]{4,6}$/')
                    ->rule(function (Get $get, ?Employee $record): \Closure {
                        return function (string $attribute, $value, \Closure $fail) use ($get, $record): void {
                            if (blank($value) || !(bool) $get('is_active')) {
                                return;
                            }

                            if (User::activePinConflictExists((string) $value, $record?->id)) {
                                $fail('رمز PIN مستخدم بالفعل بواسطة حساب نشط آخر.');
                            }
                        };
                    })
                    ->dehydrated(fn ($state) => filled($state)),
                Forms\Components\Toggle::make('is_active')
                    ->label('نشط')
                    ->default(true),
            ])->columns(2),

            \Filament\Schemas\Components\Section::make('الملف الوظيفي')
                ->relationship('employeeProfile')
                ->schema([
                    Forms\Components\TextInput::make('full_name')
                        ->label('الاسم الكامل')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('job_title')
                        ->label('المسمى الوظيفي')
                        ->maxLength(255),
                    Forms\Components\DatePicker::make('hired_at')
                        ->label('تاريخ التعيين'),
                    Forms\Components\FileUpload::make('profile_image')
                        ->label('الصورة الشخصية')
                        ->image()
                        ->directory('employees/profile-images')
                        ->imageEditor()
                        ->nullable(),
                    Forms\Components\Textarea::make('notes')
                        ->label('ملاحظات إدارية')
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(2),

            \Filament\Schemas\Components\Section::make('المرفقات')
                ->relationship('employeeProfile')
                ->schema([
                    Forms\Components\Repeater::make('attachments')
                        ->label('الملفات')
                        ->relationship()
                        ->defaultItems(0)
                        ->addActionLabel('إضافة ملف')
                        ->reorderable(false)
                        ->collapsed()
                        ->schema([
                            Forms\Components\TextInput::make('title')
                                ->label('عنوان الملف')
                                ->maxLength(255),
                            Forms\Components\FileUpload::make('file_path')
                                ->label('الملف')
                                ->directory('employees/attachments')
                                ->openable()
                                ->downloadable()
                                ->required()
                                ->afterStateUpdated(function ($state, Set $set): void {
                                    if (blank($state)) {
                                        return;
                                    }

                                    $filename = is_string($state) ? basename($state) : null;
                                    $extension = is_string($state) ? strtolower(pathinfo($state, PATHINFO_EXTENSION)) : null;

                                    if ($filename) {
                                        $set('file_name', $filename);
                                    }

                                    if ($extension) {
                                        $set('file_type', $extension);
                                    }
                                }),
                            Forms\Components\Hidden::make('file_name'),
                            Forms\Components\Hidden::make('file_type'),
                        ])->columns(2),
                ]),

            \Filament\Schemas\Components\Section::make('الدور التشغيلي')->schema([
                Forms\Components\Radio::make('staff_role')
                    ->label('نوع الحساب')
                    ->options($employeeService->assignableRoleOptions())
                    ->descriptions([
                        'employee' => 'يُستخدم كسجل موظف فقط للمزايا والوجبات دون صلاحيات تشغيلية.',
                        'cashier' => 'يستطيع استخدام نقطة البيع.',
                        'kitchen' => 'يستطيع عرض شاشة المطبخ وتحديث حالة التجهيز.',
                        'counter' => 'يستطيع عرض شاشة الكاونتر وتأكيد التسليم.',
                    ])
                    ->required()
                    ->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $businessTimezone = BusinessTime::timezone();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->manageable()->with(['roles', 'employeeProfile']))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable()->sortable(),
                Tables\Columns\ImageColumn::make('employeeProfile.profile_image')->label('الصورة')->circular()->defaultImageUrl(null),
                Tables\Columns\TextColumn::make('employeeProfile.full_name')->label('الاسم الكامل')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('employeeProfile.job_title')->label('المسمى الوظيفي')->placeholder('—'),
                Tables\Columns\TextColumn::make('username')->label('اسم المستخدم')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('current_salary')
                    ->label('الراتب الحالي')
                    ->state(function (Employee $record): ?float {
                        $salary = $record->currentEmployeeSalaryRecord();

                        return $salary ? (float) $salary->amount : null;
                    })
                    ->money('EGP')
                    ->placeholder('—')
                    ->sortable(false),
                Tables\Columns\TextColumn::make('email')->label('البريد')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('roles.display_name')->label('الدور')->badge(),
                Tables\Columns\TextColumn::make('employeeProfile.hired_at')
                    ->label('تاريخ التعيين')
                    ->date()
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')->label('نشط')->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->timezone($businessTimezone)
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('آخر تحديث')
                    ->dateTime()
                    ->timezone($businessTimezone)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('آخر تسجيل دخول')
                    ->dateTime()
                    ->timezone($businessTimezone)
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('الحالة'),
                Tables\Filters\SelectFilter::make('roles')
                    ->label('الدور')
                    ->relationship('roles', 'display_name', fn (Builder $query) => $query->whereIn('name', User::assignableEmployeeRoleNames())),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\Action::make('toggleActive')
                    ->label(fn (Employee $record) => $record->is_active ? 'تعطيل' : 'تفعيل')
                    ->icon(fn (Employee $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Employee $record) => $record->is_active ? 'danger' : 'success')
                    ->visible(fn (Employee $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->requiresConfirmation()
                    ->action(function (Employee $record): void {
                        abort_unless(auth()->user()?->can('update', $record), 403);

                        if (!$record->is_active && User::activePinConflictExists($record->pin, $record->id)) {
                            Notification::make()
                                ->title('لا يمكن تفعيل الموظف')
                                ->body('رمز PIN الحالي مستخدم بواسطة حساب نشط آخر. عدّل رمز PIN أولًا ثم أعد المحاولة.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $oldState = (bool) $record->is_active;
                        app(AdminActivityLogService::class)->withoutModelLogging(fn () => $record->update(['is_active' => !$record->is_active]));
                        $record->refresh();
                        app(AdminActivityLogService::class)->logAction(
                            action: 'toggled',
                            subject: $record,
                            description: 'تم تغيير حالة موظف من شاشة إدارة الموظفين.',
                            oldValues: ['is_active' => $oldState],
                            newValues: ['is_active' => $record->is_active],
                        );
                    }),
            ])
            ->defaultSort('name');
    }

    public static function infolist(Schema $infolist): Schema
    {
        $businessTimezone = BusinessTime::timezone();

        return $infolist->schema([
            \Filament\Schemas\Components\Section::make('ملخص HR')->schema(array_filter([
                Infolists\Components\TextEntry::make('current_salary_summary')
                    ->label('الراتب الحالي')
                    ->state(fn (Employee $record): string => $record->currentEmployeeSalaryRecord()
                        ? number_format((float) $record->currentEmployeeSalaryRecord()->amount, 2) . ' ج.م'
                        : 'غير محدد'),
                Infolists\Components\TextEntry::make('active_penalties_count_summary')
                    ->label('عدد الجزاءات النشطة')
                    ->state(fn (Employee $record): string => (string) $record->activeEmployeePenaltiesCount()),
                Infolists\Components\TextEntry::make('active_penalties_total_summary')
                    ->label('إجمالي الجزاءات النشطة')
                    ->state(fn (Employee $record): string => number_format((float) $record->activeEmployeePenaltiesTotal(), 2) . ' ج.م'),
                HrFeature::hasAdvanceTables()
                    ? Infolists\Components\TextEntry::make('active_advances_count_summary')
                        ->label('عدد السلف النشطة')
                        ->state(fn (Employee $record): string => (string) $record->activeEmployeeAdvancesCount())
                    : null,
                HrFeature::hasAdvanceTables()
                    ? Infolists\Components\TextEntry::make('active_advances_total_summary')
                        ->label('إجمالي السلف النشطة')
                        ->state(fn (Employee $record): string => number_format((float) $record->activeEmployeeAdvancesTotal(), 2) . ' ج.م')
                    : null,
            ]))->columns(3),
            \Filament\Schemas\Components\Section::make('بيانات الموظف')->schema([
                Infolists\Components\TextEntry::make('name')
                    ->label('الاسم'),
                Infolists\Components\TextEntry::make('username')
                    ->label('اسم المستخدم'),
                Infolists\Components\TextEntry::make('email')
                    ->label('البريد الإلكتروني')
                    ->placeholder('—'),
                Infolists\Components\TextEntry::make('phone')
                    ->label('الهاتف')
                    ->placeholder('—'),
                Infolists\Components\IconEntry::make('is_active')
                    ->label('نشط')
                    ->boolean(),
                Infolists\Components\TextEntry::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->timezone($businessTimezone),
            ])->columns(3),
            \Filament\Schemas\Components\Section::make('الملف الوظيفي')->schema([
                Infolists\Components\TextEntry::make('employeeProfile.full_name')
                    ->label('الاسم الكامل')
                    ->placeholder('—'),
                Infolists\Components\TextEntry::make('employeeProfile.job_title')
                    ->label('المسمى الوظيفي')
                    ->placeholder('—'),
                Infolists\Components\TextEntry::make('employeeProfile.hired_at')
                    ->label('تاريخ التعيين')
                    ->date()
                    ->placeholder('—'),
                Infolists\Components\TextEntry::make('roles.display_name')
                    ->label('الدور التشغيلي')
                    ->badge(),
                Infolists\Components\TextEntry::make('employeeProfile.notes')
                    ->label('ملاحظات إدارية')
                    ->placeholder('—')
                    ->columnSpanFull(),
            ])->columns(3),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $relations = [
            'roles',
            'employeeProfile',
            'employeeSalaries',
            'employeePenalties',
        ];

        if (HrFeature::hasAdvanceTables()) {
            $relations[] = 'employeeAdvances';
        }

        return parent::getEloquentQuery()->manageable()->with($relations);
    }

    public static function getRelations(): array
    {
        $relations = [];

        if (HrFeature::hasAdvanceTables()) {
            $relations[] = EmployeeAdvancesRelationManager::class;
        }

        if (HrFeature::hasSalaryTables()) {
            $relations[] = EmployeeSalariesRelationManager::class;
        }

        if (HrFeature::hasPenaltyTables()) {
            $relations[] = EmployeePenaltiesRelationManager::class;
        }

        return $relations;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'view' => Pages\ViewEmployee::route('/{record}'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
