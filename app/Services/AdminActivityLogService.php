<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use BackedEnum;
use DateTimeInterface;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AdminActivityLogService
{
    private const EXCLUDED_KEYS = [
        'created_at',
        'updated_at',
        'deleted_at',
        'remember_token',
    ];

    private const SENSITIVE_KEYS = [
        'password',
        'pin',
    ];

    private static bool $suppressModelLogging = false;

    public function shouldLog(bool $force = false): bool
    {
        if (self::$suppressModelLogging) {
            return false;
        }

        $actor = Auth::user();

        if (!$actor) {
            return false;
        }

        if ($force) {
            return true;
        }

        if (app()->runningUnitTests()) {
            return true;
        }

        $request = request();

        if (!$request) {
            return false;
        }

        if (Filament::getCurrentPanel()?->getId() === 'admin') {
            return true;
        }

        if ($request->routeIs('filament.admin.*')) {
            return true;
        }

        $referer = (string) $request->headers->get('referer', '');

        return str_contains($referer, '/admin');
    }

    public function withoutModelLogging(callable $callback): mixed
    {
        $previous = self::$suppressModelLogging;
        self::$suppressModelLogging = true;

        try {
            return $callback();
        } finally {
            self::$suppressModelLogging = $previous;
        }
    }

    public function logModelEvent(
        string $action,
        Model $subject,
        array $oldValues = [],
        array $newValues = [],
        ?string $description = null,
        array $meta = [],
        bool $force = false,
    ): ?AdminActivityLog {
        if (!$this->shouldLog($force) || $subject instanceof AdminActivityLog) {
            return null;
        }

        [$module, $modelLabel] = $this->moduleInfoFor($subject);

        return AdminActivityLog::query()->create([
            'actor_user_id' => Auth::id(),
            'action' => $action,
            'module' => $module,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'subject_label' => $this->subjectLabelFor($subject),
            'description' => $description ?? $this->defaultDescriptionFor($action, $modelLabel, $subject),
            'old_values' => $this->sanitizePayload($oldValues),
            'new_values' => $this->sanitizePayload($newValues),
            'meta' => $this->buildMeta($meta),
        ]);
    }

    public function logAction(
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        array $oldValues = [],
        array $newValues = [],
        array $meta = [],
        ?string $module = null,
        ?string $subjectLabel = null,
        bool $force = false,
    ): ?AdminActivityLog {
        if (!$this->shouldLog($force)) {
            return null;
        }

        [$derivedModule, $modelLabel] = $subject ? $this->moduleInfoFor($subject) : [$module, null];

        return AdminActivityLog::query()->create([
            'actor_user_id' => Auth::id(),
            'action' => $action,
            'module' => $module ?? $derivedModule,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subjectLabel ?? ($subject ? $this->subjectLabelFor($subject) : null),
            'description' => $description ?? ($modelLabel ? $this->defaultDescriptionFor($action, $modelLabel, $subject) : null),
            'old_values' => $this->sanitizePayload($oldValues),
            'new_values' => $this->sanitizePayload($newValues),
            'meta' => $this->buildMeta($meta),
        ]);
    }

    public function extractChangedValues(Model $model): array
    {
        $changedKeys = collect(array_keys($model->getChanges()))
            ->reject(fn (string $key) => in_array($key, self::EXCLUDED_KEYS, true))
            ->values()
            ->all();

        if ($changedKeys === []) {
            return ['old' => [], 'new' => []];
        }

        return [
            'old' => $this->sanitizePayload(Arr::only($model->getRawOriginal(), $changedKeys)),
            'new' => $this->sanitizePayload(Arr::only($model->getAttributes(), $changedKeys)),
        ];
    }

    public function extractModelSnapshot(Model $model): array
    {
        return $this->sanitizePayload(
            Arr::except($model->getAttributes(), self::EXCLUDED_KEYS),
        );
    }

    private function buildMeta(array $meta): array
    {
        $request = request();

        return $this->sanitizePayload(array_filter(array_merge([
            'route' => $request?->route()?->getName(),
            'path' => $request?->path(),
            'method' => $request?->method(),
            'ip' => $request?->ip(),
            'panel' => Filament::getCurrentPanel()?->getId(),
        ], $meta), fn ($value) => $value !== null && $value !== ''));
    }

    private function sanitizePayload(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            if (in_array((string) $key, self::EXCLUDED_KEYS, true)) {
                continue;
            }

            $sanitized[$key] = in_array((string) $key, self::SENSITIVE_KEYS, true)
                ? '***'
                : $this->normalizeValue($value);
        }

        return $sanitized;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof Model) {
            return [
                'id' => $value->getKey(),
                'label' => $this->subjectLabelFor($value),
            ];
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item) => $this->normalizeValue($item), $value);
        }

        return $value;
    }

    private function moduleInfoFor(Model $model): array
    {
        return match ($model::class) {
            \App\Models\User::class => ['users', 'مستخدم'],
            \App\Models\Employee::class => ['employees', 'موظف'],
            \App\Models\EmployeeProfile::class => ['employees', 'ملف موظف'],
            \App\Models\EmployeeProfileAttachment::class => ['employees', 'مرفق موظف'],
            \App\Models\Role::class => ['roles', 'دور'],
            \App\Models\Permission::class => ['permissions', 'صلاحية'],
            \App\Models\Order::class => ['orders', 'طلب'],
            \App\Models\Shift::class => ['shifts', 'وردية'],
            \App\Models\CashierDrawerSession::class => ['drawer_sessions', 'جلسة درج'],
            \App\Models\CashMovement::class => ['drawer_sessions', 'حركة نقدية'],
            \App\Models\MenuCategory::class => ['menu_categories', 'فئة قائمة'],
            \App\Models\MenuItem::class => ['menu_items', 'صنف'],
            \App\Models\MenuItemChannelPrice::class => ['menu_items', 'تسعير قناة للصنف'],
            \App\Models\InventoryItem::class => ['inventory_items', 'مادة مخزنية'],
            \App\Models\Supplier::class => ['suppliers', 'مورد'],
            \App\Models\Purchase::class => ['purchases', 'أمر شراء'],
            \App\Models\Expense::class => ['expenses', 'مصروف'],
            \App\Models\OrderSettlement::class => ['orders', 'تسوية طلب'],
            \App\Models\ExpenseCategory::class => ['expense_categories', 'فئة مصروف'],
            \App\Models\UserMealBenefitProfile::class => ['user_meal_benefit_profiles', 'ملف مزايا وجبات'],
            \App\Models\PosOrderType::class => ['pos_order_types', 'نوع طلب'],
            \App\Models\PosDevice::class => ['pos_devices', 'جهاز POS'],
            \App\Models\PaymentTerminal::class => ['payment_terminals', 'جهاز دفع'],
            default => [Str::snake(class_basename($model)), class_basename($model)],
        };
    }

    private function subjectLabelFor(Model $model): string
    {
        foreach ([
            'name',
            'display_name',
            'title',
            'username',
            'order_number',
            'session_number',
            'shift_number',
            'expense_number',
            'identifier',
            'code',
            'sku',
        ] as $field) {
            $value = data_get($model, $field);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return class_basename($model) . ' #' . $model->getKey();
    }

    private function defaultDescriptionFor(string $action, string $modelLabel, ?Model $subject = null): string
    {
        $subjectLabel = $subject ? $this->subjectLabelFor($subject) : null;

        return match ($action) {
            'created' => "تمت إضافة {$modelLabel}" . ($subjectLabel ? " {$subjectLabel}" : ''),
            'updated' => "تم تعديل {$modelLabel}" . ($subjectLabel ? " {$subjectLabel}" : ''),
            'deleted' => "تم حذف {$modelLabel}" . ($subjectLabel ? " {$subjectLabel}" : ''),
            'restored' => "تمت استعادة {$modelLabel}" . ($subjectLabel ? " {$subjectLabel}" : ''),
            'approved' => "تم اعتماد {$modelLabel}" . ($subjectLabel ? " {$subjectLabel}" : ''),
            'cancelled' => "تم إلغاء {$modelLabel}" . ($subjectLabel ? " {$subjectLabel}" : ''),
            'opened' => "تم فتح {$modelLabel}" . ($subjectLabel ? " {$subjectLabel}" : ''),
            'closed' => "تم إغلاق {$modelLabel}" . ($subjectLabel ? " {$subjectLabel}" : ''),
            default => "تم تنفيذ إجراء {$action}" . ($subjectLabel ? " على {$subjectLabel}" : ''),
        };
    }
}
