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

        $admin = User::updateOrCreate(
            ['email' => 'admin@pos.com'],
            [
                'name' => 'Admin Cashier',
                'username' => 'admin',
                'password' => Hash::make('password'),
                'pin' => '1234', // Required for POS login screen
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
        foreach ([
            [
                'name' => 'تيك أواي',
                'type' => 'takeaway',
                'source' => 'pos',
                'sort_order' => 1,
            ],
            [
                'name' => 'استلام',
                'type' => 'pickup',
                'source' => 'pos',
                'sort_order' => 2,
            ],
            [
                'name' => 'توصيل',
                'type' => 'delivery',
                'source' => 'pos',
                'sort_order' => 3,
            ],
        ] as $orderType) {
            PosOrderType::updateOrCreate(
                ['name' => $orderType['name']],
                array_merge($orderType, ['is_active' => true]),
            );
        }
    }

    private function seedRolesAndPermissions(): void
    {
        foreach ([
            ['name' => 'admin', 'display_name' => 'Administrator'],
            ['name' => 'manager', 'display_name' => 'Manager'],
            ['name' => 'cashier', 'display_name' => 'Cashier'],
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
    }

}
