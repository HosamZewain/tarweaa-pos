<?php

namespace App\Support;

class SystemPermissions
{
    public static function all(): array
    {
        return array_values(array_reduce(
            array_merge(
                static::resourcePermissions(),
                static::pagePermissions(),
                static::actionPermissions(),
            ),
            function (array $carry, array $permission): array {
                $carry[$permission['name']] = $permission;

                return $carry;
            },
            [],
        ));
    }

    public static function resourcePermissions(): array
    {
        $actions = [
            'viewAny' => 'عرض قائمة %s',
            'view' => 'عرض %s',
            'create' => 'إنشاء %s',
            'update' => 'تعديل %s',
            'delete' => 'حذف %s',
            'restore' => 'استعادة %s',
            'forceDelete' => 'حذف نهائي %s',
        ];

        $resources = [
            'admin_activity_logs' => ['group' => 'الإدارة', 'singular' => 'سجل نشاط إداري', 'plural' => 'سجل النشاط الإداري'],
            'users' => ['group' => 'الإدارة', 'singular' => 'مستخدم', 'plural' => 'المستخدمين'],
            'employees' => ['group' => 'الإدارة', 'singular' => 'موظف', 'plural' => 'الموظفون'],
            'roles' => ['group' => 'الإدارة', 'singular' => 'دور', 'plural' => 'الأدوار'],
            'permissions' => ['group' => 'الإدارة', 'singular' => 'صلاحية', 'plural' => 'الصلاحيات'],
            'orders' => ['group' => 'العمليات', 'singular' => 'طلب', 'plural' => 'الطلبات'],
            'shifts' => ['group' => 'العمليات', 'singular' => 'وردية', 'plural' => 'الورديات'],
            'drawer_sessions' => ['group' => 'العمليات', 'singular' => 'جلسة درج', 'plural' => 'جلسات الدرج'],
            'menu_categories' => ['group' => 'القائمة', 'singular' => 'فئة قائمة', 'plural' => 'فئات القائمة'],
            'menu_items' => ['group' => 'القائمة', 'singular' => 'صنف', 'plural' => 'الأصناف'],
            'inventory_items' => ['group' => 'المخزون', 'singular' => 'مادة مخزنية', 'plural' => 'المخزون'],
            'inventory_locations' => ['group' => 'المخزون', 'singular' => 'موقع مخزني', 'plural' => 'مواقع المخزون'],
            'inventory_transactions' => ['group' => 'المخزون', 'singular' => 'حركة مخزون', 'plural' => 'حركات المخزون'],
            'inventory_transfers' => ['group' => 'المخزون', 'singular' => 'تحويل مخزون', 'plural' => 'تحويلات المخزون'],
            'suppliers' => ['group' => 'المخزون', 'singular' => 'مورد', 'plural' => 'الموردين'],
            'purchases' => ['group' => 'المخزون', 'singular' => 'أمر شراء', 'plural' => 'المشتريات'],
            'expenses' => ['group' => 'المالية', 'singular' => 'مصروف', 'plural' => 'المصروفات'],
            'expense_categories' => ['group' => 'المالية', 'singular' => 'فئة مصروف', 'plural' => 'فئات المصروفات'],
            'user_meal_benefit_profiles' => ['group' => 'الإعدادات', 'singular' => 'ملف مزايا وجبات', 'plural' => 'ملفات مزايا الوجبات'],
            'pos_order_types' => ['group' => 'الإعدادات', 'singular' => 'نوع طلب', 'plural' => 'أنواع الطلبات'],
            'pos_devices' => ['group' => 'الإعدادات', 'singular' => 'جهاز POS', 'plural' => 'أجهزة POS'],
            'payment_terminals' => ['group' => 'الإعدادات', 'singular' => 'جهاز دفع', 'plural' => 'أجهزة الدفع'],
        ];

        $permissions = [];

        foreach ($resources as $prefix => $meta) {
            foreach ($actions as $action => $label) {
                $subject = $action === 'viewAny' ? $meta['plural'] : $meta['singular'];

                $permissions[] = [
                    'name' => "{$prefix}.{$action}",
                    'display_name' => sprintf($label, $subject),
                    'group' => $meta['group'],
                ];
            }
        }

        return $permissions;
    }

    public static function pagePermissions(): array
    {
        return [
            ['name' => 'dashboard.view', 'display_name' => 'عرض لوحة التحكم', 'group' => 'لوحة التحكم'],
            ['name' => 'dashboard.analytics.view', 'display_name' => 'عرض مؤشرات وتقارير لوحة التحكم', 'group' => 'لوحة التحكم'],
            ['name' => 'reports.sales.view', 'display_name' => 'عرض تقرير المبيعات', 'group' => 'التقارير'],
            ['name' => 'reports.sales_breakdown.view', 'display_name' => 'عرض تفصيل المبيعات', 'group' => 'التقارير'],
            ['name' => 'reports.discounts.view', 'display_name' => 'عرض تقرير الخصومات', 'group' => 'التقارير'],
            ['name' => 'reports.drawer_reconciliation.view', 'display_name' => 'عرض تسوية الأدراج', 'group' => 'التقارير'],
            ['name' => 'reports.expenses.view', 'display_name' => 'عرض تقرير المصروفات', 'group' => 'التقارير'],
            ['name' => 'reports.inventory.view', 'display_name' => 'عرض تقرير المخزون', 'group' => 'التقارير'],
            ['name' => 'reports.inventory_movements.view', 'display_name' => 'عرض حركة المخزون', 'group' => 'التقارير'],
            ['name' => 'reports.inventory_locations.view', 'display_name' => 'عرض تقرير المخزون متعدد المواقع', 'group' => 'التقارير'],
            ['name' => 'reports.card_terminals.view', 'display_name' => 'عرض تقرير أجهزة الدفع', 'group' => 'التقارير'],
            ['name' => 'reports.platform_transfers.view', 'display_name' => 'عرض تقرير تحويلات المنصات', 'group' => 'التقارير'],
            ['name' => 'settings.database_backups.manage', 'display_name' => 'إدارة النسخ الاحتياطية لقاعدة البيانات', 'group' => 'الإعدادات'],
        ];
    }

    public static function actionPermissions(): array
    {
        return [
            ['name' => 'shifts.open', 'display_name' => 'فتح وردية', 'group' => 'العمليات'],
            ['name' => 'shifts.close', 'display_name' => 'إغلاق وردية', 'group' => 'العمليات'],
            ['name' => 'drawers.open', 'display_name' => 'فتح درج لكاشير آخر', 'group' => 'العمليات'],
            ['name' => 'drawers.close', 'display_name' => 'إغلاق درج', 'group' => 'العمليات'],
            ['name' => 'drawers.cash_in', 'display_name' => 'إضافة نقدية للدرج', 'group' => 'العمليات'],
            ['name' => 'drawers.cash_out', 'display_name' => 'سحب نقدية من الدرج', 'group' => 'العمليات'],
            ['name' => 'orders.cancel', 'display_name' => 'إلغاء طلب', 'group' => 'العمليات'],
            ['name' => 'orders.record_payment', 'display_name' => 'تسجيل دفعة يدوية على طلب', 'group' => 'العمليات'],
            ['name' => 'orders.apply_special_settlement', 'display_name' => 'تطبيق تسويات بدل الوجبات والتحميل', 'group' => 'العمليات'],
            ['name' => 'apply_discount', 'display_name' => 'تطبيق الخصم', 'group' => 'العمليات'],
            ['name' => 'expenses.approve', 'display_name' => 'الموافقة على مصروف', 'group' => 'المالية'],
            ['name' => 'inventory_items.adjust_stock', 'display_name' => 'تعديل المخزون', 'group' => 'المخزون'],
            ['name' => 'inventory_items.add_stock', 'display_name' => 'إضافة مخزون', 'group' => 'المخزون'],
            ['name' => 'view_kitchen', 'display_name' => 'عرض شاشة المطبخ', 'group' => 'المطبخ'],
            ['name' => 'mark_order_ready', 'display_name' => 'تحديد الطلب كجاهز', 'group' => 'المطبخ'],
            ['name' => 'view_counter_screen', 'display_name' => 'عرض شاشة التسليم والاستلام', 'group' => 'الكاونتر'],
            ['name' => 'handover_counter_orders', 'display_name' => 'تسليم الطلبات من الكاونتر', 'group' => 'الكاونتر'],
            ['name' => 'reports.meal_benefits.view', 'display_name' => 'عرض كشف بدلات الوجبات والتحميل', 'group' => 'التقارير'],
        ];
    }
}
