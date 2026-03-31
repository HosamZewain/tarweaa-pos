<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Role;
use App\Models\User;
use App\Support\BusinessTime;
use App\Services\AdminActivityLogService;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Filament\Notifications\Notification;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static string | \UnitEnum | null $navigationGroup = 'الإدارة';
    protected static ?string $navigationLabel = 'المستخدمون';
    protected static ?string $modelLabel = 'مستخدم';
    protected static ?string $pluralModelLabel = 'المستخدمون';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            \Filament\Schemas\Components\Section::make('بيانات المستخدم')->schema([
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
                    ->dehydrateStateUsing(fn ($state) => !empty($state) ? Hash::make($state) : null)
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->maxLength(255),
                Forms\Components\TextInput::make('pin')
                    ->label('رمز PIN')
                    ->password()
                    ->helperText('اختياري. يستخدم لتسجيل دخول الكاشير من شاشة الـ POS ويجب أن يكون من 4 إلى 6 أرقام، ويجب أن يكون فريدًا بين الحسابات النشطة.')
                    ->minLength(4)
                    ->maxLength(6)
                    ->rule('regex:/^[0-9]{4,6}$/')
                    ->rule(function (Get $get, ?User $record): \Closure {
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

            \Filament\Schemas\Components\Section::make('الأدوار')->schema([
                Forms\Components\CheckboxList::make('roles')
                    ->label('الأدوار')
                    ->relationship('roles', 'display_name')
                    ->columns(3),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $businessTimezone = BusinessTime::timezone();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutOperationalEmployeeRoles()->with('roles'))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('username')->label('اسم المستخدم')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->label('البريد')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('roles.display_name')->label('الأدوار')->badge(),
                Tables\Columns\IconColumn::make('is_active')->label('نشط')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('تاريخ الإنشاء')->dateTime()->timezone($businessTimezone)->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->label('آخر تحديث')->dateTime()->timezone($businessTimezone)->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('last_login_at')->label('آخر تسجيل دخول')->dateTime()->timezone($businessTimezone)->sortable()->placeholder('—')->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('الحالة'),
                Tables\Filters\SelectFilter::make('roles')
                    ->label('الدور')
                    ->relationship('roles', 'display_name'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\Action::make('toggleActive')
                    ->label(fn (User $record) => $record->is_active ? 'تعطيل' : 'تفعيل')
                    ->icon(fn (User $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (User $record) => $record->is_active ? 'danger' : 'success')
                    ->visible(fn (User $record): bool => static::canEdit($record) && auth()->id() !== $record->id)
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        if (!$record->is_active && User::activePinConflictExists($record->pin, $record->id)) {
                            Notification::make()
                                ->title('لا يمكن تفعيل المستخدم')
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
                            description: 'تم تغيير حالة المستخدم من لوحة الإدارة.',
                            oldValues: ['is_active' => $oldState],
                            newValues: ['is_active' => $record->is_active],
                        );
                    }),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutOperationalEmployeeRoles()
            ->with('roles');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
