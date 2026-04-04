<?php

namespace App\Providers;

use App\Models\CashierDrawerSession;
use App\Models\Employee;
use App\Models\EmployeePenalty;
use App\Models\EmployeeProfile;
use App\Models\EmployeeProfileAttachment;
use App\Models\EmployeeSalary;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryTransfer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemChannelPrice;
use App\Models\PaymentTerminal;
use App\Models\Permission;
use App\Models\PosDevice;
use App\Models\PosOrderType;
use App\Models\ProductionBatch;
use App\Models\ProductionRecipe;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserMealBenefitProfile;
use App\Observers\AdminActivityObserver;
use App\Services\PortalAccessService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
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
            EmployeeSalary::class,
            EmployeePenalty::class,
            EmployeeProfile::class,
            EmployeeProfileAttachment::class,
            Role::class,
            Permission::class,
            Shift::class,
            CashierDrawerSession::class,
            MenuCategory::class,
            MenuItem::class,
            MenuItemChannelPrice::class,
            InventoryItem::class,
            ProductionRecipe::class,
            ProductionBatch::class,
            InventoryLocation::class,
            InventoryTransfer::class,
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

        View::composer('layouts.app', function ($view): void {
            if (!Auth::check()) {
                return;
            }

            $user = Auth::user();

            if (!$user instanceof User) {
                return;
            }

            $portalAccessService = app(PortalAccessService::class);
            $session = request()->session();

            if (!$portalAccessService->hasAnyAccess($user)) {
                return;
            }

            $view
                ->with('portalBootstrapAuth', $portalAccessService->getFrontendBootstrapPayload($user, $session))
                ->with('portalHomeUrl', $portalAccessService->resolveHome($user));
        });
    }
}
