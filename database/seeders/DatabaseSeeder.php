<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\PosDevice;
use App\Models\PosOrderType;
use App\Models\Shift;
use App\Models\User;
use App\Models\Role;
use App\Support\SystemPermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedRolesAndPermissions();
        $this->seedOperationalDefaults();

        $admin = User::firstOrCreate(
            ['email' => 'admin@pos.com'],
            [
                'name' => 'Admin Cashier',
                'username' => 'admin',
                'password' => Hash::make('password'),
                'pin' => '1234',
                'phone' => '0000000000',
                'is_active' => true,
            ]
        );

        // Assign Admin role
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Administrator']
        );
        
        if (!$admin->roles->contains($adminRole->id)) {
            $admin->roles()->attach($adminRole->id);
        }

        $device = PosDevice::updateOrCreate(
            ['identifier' => 'POS-01'],
            [
                'name' => 'Main Register',
                'is_active' => true,
            ]
        );

        // Ensure there is at least one open shift for today
        $openShift = Shift::where('status', 'open')->first();
        if (!$openShift) {
            Shift::create([
                'shift_number' => 'SHIFT-' . date('Ymd') . '-01',
                'status' => 'open',
                'opened_by' => $admin->id,
                'started_at' => now(),
            ]);
        }
    }

    private function seedOperationalDefaults(): void
    {
        if (PosOrderType::withTrashed()->exists()) {
            return;
        }

        foreach ([
            [
                'name' => 'تيك أواي',
                'type' => 'takeaway',
                'source' => 'pos',
                'sort_order' => 1,
                'is_default' => true,
            ],
            [
                'name' => 'استلام',
                'type' => 'pickup',
                'source' => 'pos',
                'sort_order' => 2,
                'is_default' => false,
            ],
            [
                'name' => 'توصيل',
                'type' => 'delivery',
                'source' => 'pos',
                'sort_order' => 3,
                'is_default' => false,
            ],
        ] as $orderType) {
            PosOrderType::create(array_merge($orderType, ['is_active' => true]));
        }
    }

    private function seedRolesAndPermissions(): void
    {
        foreach ([
            ['name' => 'owner', 'display_name' => 'Owner', 'show_in_employee_resource' => false],
            ['name' => 'admin', 'display_name' => 'Administrator', 'show_in_employee_resource' => false],
            ['name' => 'manager', 'display_name' => 'Manager', 'show_in_employee_resource' => false],
            ['name' => 'cashier', 'display_name' => 'Cashier', 'show_in_employee_resource' => true],
            ['name' => 'kitchen', 'display_name' => 'Kitchen', 'show_in_employee_resource' => true],
            ['name' => 'counter', 'display_name' => 'Counter', 'show_in_employee_resource' => true],
            ['name' => 'employee', 'display_name' => 'Employee', 'show_in_employee_resource' => true],
        ] as $roleData) {
            Role::updateOrCreate(
                ['name' => $roleData['name']],
                $roleData,
            );
        }

        foreach (SystemPermissions::all() as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                [
                    'display_name' => $permission['display_name'],
                    'group' => $permission['group'],
                ],
            );
        }

        $this->syncDefaultRolePermissions();
    }

    private function syncDefaultRolePermissions(): void
    {
        $rolePermissions = [
            'owner' => [
                'dashboard.view',
                'dashboard.analytics.view',
                'hr.overview.view',
                'employees.viewAny',
                'employees.view',
                'employees.create',
                'employees.update',
                'employee_salaries.viewAny',
                'employee_salaries.view',
                'employee_salaries.create',
                'employee_salaries.update',
                'employee_penalties.viewAny',
                'employee_penalties.view',
                'employee_penalties.create',
                'employee_penalties.update',
                'user_meal_benefit_profiles.viewAny',
                'user_meal_benefit_profiles.view',
                'user_meal_benefit_profiles.create',
                'user_meal_benefit_profiles.update',
                'reports.meal_benefits.view',
            ],
            'manager' => [
                'dashboard.view',
                'hr.overview.view',
                'employees.viewAny',
                'employees.view',
                'employees.create',
                'employees.update',
                'employee_salaries.viewAny',
                'employee_salaries.view',
                'employee_salaries.create',
                'employee_salaries.update',
                'employee_penalties.viewAny',
                'employee_penalties.view',
                'employee_penalties.create',
                'employee_penalties.update',
                'user_meal_benefit_profiles.viewAny',
                'user_meal_benefit_profiles.view',
                'user_meal_benefit_profiles.create',
                'user_meal_benefit_profiles.update',
                'reports.meal_benefits.view',
            ],
            'kitchen' => [
                'view_kitchen',
                'mark_order_ready',
            ],
            'counter' => [
                'view_counter_screen',
                'handover_counter_orders',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::firstWhere('name', $roleName);

            if ($role) {
                $role->givePermissionTo($permissions);

                if ($roleName === 'manager' && $role->permissions()->where('name', 'dashboard.analytics.view')->exists()) {
                    $role->revokePermissionTo('dashboard.analytics.view');
                }
            }
        }
    }

}
