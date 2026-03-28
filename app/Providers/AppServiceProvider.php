<?php

namespace App\Providers;

use App\Models\CashierDrawerSession;
use App\Models\Employee;
use App\Models\EmployeeProfile;
use App\Models\EmployeeProfileAttachment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryItem;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\PaymentTerminal;
use App\Models\Permission;
use App\Models\PosDevice;
use App\Models\PosOrderType;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserMealBenefitProfile;
use App\Observers\AdminActivityObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([
            User::class,
            Employee::class,
            EmployeeProfile::class,
            EmployeeProfileAttachment::class,
            Role::class,
            Permission::class,
            Shift::class,
            CashierDrawerSession::class,
            MenuCategory::class,
            MenuItem::class,
            InventoryItem::class,
            Supplier::class,
            Purchase::class,
            Expense::class,
            ExpenseCategory::class,
            UserMealBenefitProfile::class,
            PosOrderType::class,
            PosDevice::class,
            PaymentTerminal::class,
        ] as $modelClass) {
            $modelClass::observe(AdminActivityObserver::class);
        }
    }
}
