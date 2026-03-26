<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Models\Permission;
use App\Models\Role;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shield-check';
    protected static string | \UnitEnum | null $navigationGroup = 'الإدارة';
    protected static ?string $navigationLabel = 'الأدوار';
    protected static ?string $modelLabel = 'دور';
    protected static ?string $pluralModelLabel = 'الأدوار';
    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            \Filament\Schemas\Components\Section::make('بيانات الدور')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('الاسم البرمجي')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50)
                    ->helperText('معرّف النظام — لا يقبل مسافات'),
                Forms\Components\TextInput::make('display_name')
                    ->label('الاسم المعروض')
                    ->required()
                    ->maxLength(100),
                Forms\Components\Textarea::make('description')
                    ->label('الوصف')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_active')
                    ->label('نشط')
                    ->default(true),
            ])->columns(2),

            \Filament\Schemas\Components\Section::make('الصلاحيات')->schema([
                \Filament\Schemas\Components\Tabs::make('تصنيفات الصلاحيات')
                    ->persistTabInQueryString('permissions-tab')
                    ->tabs(static::getPermissionTabs())
                    ->columnSpanFull(),
            ])
                ->description('تم تقسيم الصلاحيات حسب الفئة لتسهيل الإضافة والإزالة من الدور.')
                ->columnSpanFull(),
        ]);
    }

    public static function getPermissionTabs(): array
    {
        return static::getPermissionGroups()
            ->map(function (Collection $permissions, string $group): \Filament\Schemas\Components\Tabs\Tab {
                $stateKey = static::getPermissionGroupStateKey($group);

                return \Filament\Schemas\Components\Tabs\Tab::make($group ?: 'عام')
                    ->badge((string) $permissions->count())
                    ->schema([
                        Forms\Components\CheckboxList::make("permission_groups.{$stateKey}")
                            ->label(' ')
                            ->options($permissions->pluck('display_name', 'id')->toArray())
                            ->descriptions($permissions->pluck('name', 'id')->toArray())
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(2)
                            ->gridDirection('row')
                            ->helperText("عدد الصلاحيات في هذه الفئة: {$permissions->count()}"),
                    ]);
            })
            ->values()
            ->all();
    }

    public static function getPermissionGroups(): Collection
    {
        return Permission::query()
            ->orderBy('group')
            ->orderBy('display_name')
            ->get()
            ->groupBy(fn (Permission $permission) => $permission->group ?: 'عام');
    }

    public static function getPermissionGroupStateKey(string $group): string
    {
        $slug = Str::slug(Str::transliterate($group, strict: true), '_');

        return filled($slug) ? $slug : 'group_' . substr(md5($group), 0, 8);
    }

    public static function getPermissionGroupState(Role $role): array
    {
        $assignedIds = $role->permissions()->pluck('permissions.id')->map(fn ($id) => (string) $id);

        return static::getPermissionGroups()
            ->mapWithKeys(function (Collection $permissions, string $group) use ($assignedIds): array {
                $stateKey = static::getPermissionGroupStateKey($group);

                return [
                    $stateKey => $permissions
                        ->pluck('id')
                        ->map(fn ($id) => (string) $id)
                        ->intersect($assignedIds)
                        ->values()
                        ->all(),
                ];
            })
            ->all();
    }

    public static function extractPermissionIds(array $data): array
    {
        return collect($data['permission_groups'] ?? [])
            ->flatten()
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('المعرّف')
                    ->searchable()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('الصلاحيات')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('المستخدمون')
                    ->badge()
                    ->color('success')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('الحالة'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make()
                    ->visible(fn (Role $record): bool => static::canDelete($record) && $record->users()->doesntExist()),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('display_name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit'   => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
