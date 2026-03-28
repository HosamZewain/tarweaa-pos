<x-filament-panels::page>
    <div class="admin-page-shell">
        @if (session('database_restore_status'))
            @php($restoreStatus = session('database_restore_status'))
            <div @class([
                'mb-4 rounded-2xl border px-4 py-3 text-sm',
                'border-success-200 bg-success-50 text-success-800 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-200' => ($restoreStatus['type'] ?? null) === 'success',
                'border-danger-200 bg-danger-50 text-danger-800 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-200' => ($restoreStatus['type'] ?? null) === 'error',
            ])>
                <p class="font-semibold">{{ $restoreStatus['title'] ?? 'تم تحديث الحالة' }}</p>
                @if (!empty($restoreStatus['message'] ?? null))
                    <p class="mt-1">{{ $restoreStatus['message'] }}</p>
                @endif
            </div>
        @endif

        <x-admin.report-header
            title="النسخ الاحتياطية والاستعادة"
            description="إنشاء نسخة احتياطية مباشرة من قاعدة البيانات الحالية، أو رفع ملف SQL لاستعادة نسخة سابقة عند الحاجة."
            :meta="['ينصح بأخذ نسخة جديدة قبل أي استعادة', 'الاستعادة تستبدل البيانات الحالية بالكامل']"
        />

        <div class="admin-metric-grid">
            <x-admin.metric-card
                title="عدد النسخ المتاحة"
                :value="number_format(count($backupFiles))"
                hint="الملفات المخزنة داخل النظام"
                tone="primary"
            />

            <x-admin.metric-card
                title="أحدث نسخة"
                :value="$backupFiles[0]['last_modified_human'] ?? '—'"
                hint="تاريخ آخر نسخة احتياطية"
                tone="info"
            />
        </div>

        <div class="admin-filter-card">
            <div class="admin-filter-card__header">
                <div>
                    <h3 class="admin-filter-card__title">إنشاء نسخة جديدة</h3>
                    <p class="admin-filter-card__description">أنشئ ملف SQL جديد يحتوي على بنية البيانات والبيانات الحالية، ثم نزّله فورًا إلى جهازك.</p>
                </div>

                <x-admin.badge tone="primary">تنزيل مباشر</x-admin.badge>
            </div>

            <div class="admin-filter-card__actions">
                <x-filament::button wire:click="createBackup" icon="heroicon-o-arrow-down-tray">
                    إنشاء وتنزيل نسخة احتياطية
                </x-filament::button>

                <x-filament::button color="gray" wire:click="refreshBackupFiles" icon="heroicon-o-arrow-path">
                    تحديث القائمة
                </x-filament::button>
            </div>
        </div>

        <div class="admin-filter-card">
            <div class="admin-filter-card__header">
                <div>
                    <h3 class="admin-filter-card__title">استعادة نسخة احتياطية</h3>
                    <p class="admin-filter-card__description">ارفع ملف SQL ثم أكّد العملية يدويًا. سيتم أخذ نسخة أمان تلقائيًا قبل بدء الاستعادة. تم استخدام رفع مباشر لتفادي مشاكل Livewire مع ملفات SQL الكبيرة أو غير المعروفة النوع.</p>
                </div>

                <x-admin.badge tone="warning">عملية حساسة</x-admin.badge>
            </div>

            <form method="POST" action="{{ route('admin.database-backups.restore') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <label for="restore_backup_file" class="text-sm font-medium text-gray-900 dark:text-white">
                            ملف النسخة الاحتياطية
                        </label>
                        <input
                            id="restore_backup_file"
                            name="restore_backup_file"
                            type="file"
                            accept=".sql,.txt,application/sql,application/x-sql,text/plain,text/x-sql,application/octet-stream"
                            class="block w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            required
                        >
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            ارفع ملف SQL تم تصديره من النظام. إذا لم يتعرف المتصفح على النوع، يمكن اختيار ملف <code>.sql</code> أو <code>.txt</code>.
                        </p>
                        @error('restore_backup_file')
                            <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label for="restore_confirmation" class="text-sm font-medium text-gray-900 dark:text-white">
                            تأكيد الاستعادة
                        </label>
                        <input
                            id="restore_confirmation"
                            name="restore_confirmation"
                            type="text"
                            value="{{ old('restore_confirmation') }}"
                            placeholder="اكتب RESTORE"
                            class="block w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            required
                        >
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            هذه العملية ستستبدل بيانات قاعدة البيانات الحالية بالكامل.
                        </p>
                        @error('restore_confirmation')
                            <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="admin-filter-card__actions">
                    <x-filament::button type="submit" color="danger" icon="heroicon-o-arrow-up-tray">
                        رفع واستعادة النسخة
                    </x-filament::button>
                </div>
            </form>
        </div>

        <div class="admin-filter-card">
            <div class="admin-filter-card__header">
                <div>
                    <h3 class="admin-filter-card__title">إعادة تهيئة البيانات التشغيلية</h3>
                    <p class="admin-filter-card__description">احذف بيانات التشغيل التجريبية للبدء من جديد، مع الإبقاء على المستخدمين، الأدوار، الصلاحيات، المينيو، والمكونات الأساسية.</p>
                </div>

                <x-admin.badge tone="danger">حذف تشغيلي</x-admin.badge>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="admin-table-scroll">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>سيتم حذف</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($resetSummary['deleted_tables'] ?? [] as $table)
                                <tr>
                                    <td>{{ $table }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="admin-table-scroll">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>سيبقى محفوظًا</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($resetSummary['preserved_tables'] ?? [] as $table)
                                <tr>
                                    <td>{{ $table }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
                <x-filament::input.wrapper>
                    <x-filament::input
                        wire:model.live="reset_confirmation"
                        placeholder="اكتب RESET"
                    />
                </x-filament::input.wrapper>

                <x-filament::button color="danger" wire:click="resetOperationalData" icon="heroicon-o-trash">
                    حذف البيانات التشغيلية
                </x-filament::button>
            </div>

            <p class="mt-3 text-sm text-danger-600 dark:text-danger-400">
                سيتم إنشاء نسخة أمان تلقائية قبل الحذف. هذه العملية تحذف الطلبات، الورديات، جلسات الدرج، المصروفات، العملاء، وحركات التشغيل التجريبية.
            </p>
        </div>

        <x-admin.table-card
            heading="النسخ المتوفرة"
            description="النسخ الاحتياطية الموجودة حاليًا داخل مساحة التخزين الخاصة بالتطبيق."
            :count="count($backupFiles)"
        >
            @if (empty($backupFiles))
                <x-admin.empty-state title="لا توجد نسخ احتياطية محفوظة بعد" />
            @else
                <div class="admin-table-scroll">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>اسم الملف</th>
                                <th>الحجم</th>
                                <th>تاريخ الإنشاء</th>
                                <th>الإجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($backupFiles as $backup)
                                <tr>
                                    <td class="font-semibold text-gray-900 dark:text-white">{{ $backup['name'] }}</td>
                                    <td>{{ $backup['size_human'] }}</td>
                                    <td>{{ $backup['last_modified_human'] }}</td>
                                    <td>
                                        <x-filament::button
                                            size="sm"
                                            color="gray"
                                            icon="heroicon-o-arrow-down-tray"
                                            wire:click="downloadBackup('{{ $backup['path'] }}')"
                                        >
                                            تنزيل
                                        </x-filament::button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.table-card>
    </div>
</x-filament-panels::page>
