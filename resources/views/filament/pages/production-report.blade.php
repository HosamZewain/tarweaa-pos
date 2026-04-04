<x-filament-panels::page>
    @php
        $batches = $reportData['batches'] ?? ['entries' => collect(), 'by_prepared_item' => collect(), 'by_location' => collect(), 'summary' => []];
        $preparedStock = collect($reportData['prepared_stock'] ?? []);
        $consumption = $reportData['consumption'] ?? ['entries' => collect(), 'by_raw_item' => collect(), 'summary' => []];
    @endphp

    <div class="admin-page-shell">
        <x-admin.report-header
            title="تقرير الإنتاج والتحضير"
            description="متابعة دفعات التحضير وتكلفة الإنتاج واستهلاك المواد الخام وأرصدة المنتجات المُحضّرة."
            :from="$date_from"
            :to="$date_to"
            :meta="['يعتمد على دفعات الإنتاج المنفذة فقط', 'يعرض تكلفة التشغيلة والعائد', 'يفصل بين المخزون المُحضّر واستهلاك الخامات']"
        />

        <div class="admin-filter-card">
            <div class="admin-filter-card__header">
                <div>
                    <h3 class="admin-filter-card__title">فلاتر التقرير</h3>
                    <p class="admin-filter-card__description">حدد الفترة والموقع والمنتج المُحضّر لمراجعة بيانات الإنتاج بدقة.</p>
                </div>

                <x-admin.badge tone="warning">إنتاج وتحضير</x-admin.badge>
            </div>

            <form wire:submit="generateReport">
                {{ $this->form }}

                <div class="admin-filter-card__actions">
                    <x-filament::button type="submit" icon="heroicon-o-funnel">
                        عرض التقرير
                    </x-filament::button>
                </div>
            </form>
        </div>

        <div class="admin-metric-grid xl:grid-cols-6">
            <x-admin.metric-card
                title="دفعات مكتملة"
                :value="number_format($batches['summary']['batches_count'] ?? 0)"
                hint="ضمن الفترة المحددة"
                tone="primary"
            />
            <x-admin.metric-card
                title="إجمالي الناتج"
                :value="number_format($batches['summary']['total_output_quantity'] ?? 0, 3)"
                hint="مجموع الكميات المنتجة"
                tone="success"
            />
            <x-admin.metric-card
                title="تكلفة المدخلات"
                :value="number_format($batches['summary']['total_input_cost'] ?? 0, 2) . ' ج.م'"
                hint="إجمالي تكلفة الخامات"
                tone="info"
            />
            <x-admin.metric-card
                title="متوسط تكلفة الناتج"
                :value="number_format($batches['summary']['average_unit_cost'] ?? 0, 2) . ' ج.م'"
                hint="متوسط تكلفة الوحدة المنتجة"
                tone="warning"
            />
            <x-admin.metric-card
                title="إجمالي الفاقد"
                :value="number_format($batches['summary']['total_waste_quantity'] ?? 0, 3)"
                hint="فاقد التحضير المسجل"
                tone="danger"
            />
            <x-admin.metric-card
                title="عائد أعلى من القياسي"
                :value="number_format($batches['summary']['positive_yield_batches'] ?? 0)"
                hint="دفعات بإنتاج أعلى من المتوقع"
                tone="success"
            />
            <x-admin.metric-card
                title="عائد أقل من القياسي"
                :value="number_format($batches['summary']['negative_yield_batches'] ?? 0)"
                hint="دفعات بإنتاج أقل من المتوقع"
                tone="danger"
            />
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-admin.table-card
                heading="ملخص المنتجات المُحضّرة"
                description="تجميع الإنتاج حسب المنتج المُحضّر."
                :count="collect($batches['by_prepared_item'] ?? [])->count()"
            >
                <div class="admin-table-scroll">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>المنتج المُحضّر</th>
                                <th>عدد الدفعات</th>
                                <th>إجمالي الناتج</th>
                                <th>إجمالي الفاقد</th>
                                <th>تكلفة المدخلات</th>
                                <th>متوسط تكلفة الوحدة</th>
                                <th>متوسط فرق العائد</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($batches['by_prepared_item'] ?? [] as $row)
                                <tr>
                                    <td>{{ $row['prepared_item_name'] }}</td>
                                    <td>{{ number_format($row['batches_count']) }}</td>
                                    <td>{{ number_format($row['total_output_quantity'], 3) }}</td>
                                    <td>{{ number_format($row['total_waste_quantity'], 3) }}</td>
                                    <td>{{ number_format($row['total_input_cost'], 2) }} ج.م</td>
                                    <td>{{ number_format($row['average_unit_cost'], 2) }} ج.م</td>
                                    <td>{{ number_format($row['average_yield_variance_percentage'], 2) }}%</td>
                                </tr>
                            @empty
                                <tr><td colspan="7">لا توجد دفعات إنتاج</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.table-card>

            <x-admin.table-card
                heading="استهلاك المواد الخام"
                description="إجمالي استهلاك الخامات في الإنتاج خلال الفترة."
                :count="collect($consumption['by_raw_item'] ?? [])->count()"
            >
                <div class="admin-table-scroll">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>المادة الخام</th>
                                <th>عدد مرات الاستخدام</th>
                                <th>الكمية المستهلكة</th>
                                <th>إجمالي التكلفة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($consumption['by_raw_item'] ?? [] as $row)
                                <tr>
                                    <td>{{ $row['item_name'] }}</td>
                                    <td>{{ number_format($row['lines_count']) }}</td>
                                    <td>{{ number_format($row['consumed_quantity'], 6) }} {{ $row['unit'] }}</td>
                                    <td>{{ number_format($row['consumed_cost'], 2) }} ج.م</td>
                                </tr>
                            @empty
                                <tr><td colspan="4">لا توجد حركة استهلاك إنتاج</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.table-card>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-admin.table-card
                heading="أرصدة المنتجات المُحضّرة"
                description="الرصيد الحالي للمنتجات المُحضّرة داخل المواقع مع قيمة المخزون."
                :count="$preparedStock->count()"
            >
                <div class="admin-table-scroll">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>الموقع</th>
                                <th>المنتج المُحضّر</th>
                                <th>الرصيد</th>
                                <th>تكلفة الوحدة</th>
                                <th>القيمة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($preparedStock as $row)
                                <tr>
                                    <td>{{ $row['location_name'] }}</td>
                                    <td>{{ $row['item_name'] }}</td>
                                    <td>{{ number_format($row['current_stock'], 3) }} {{ $row['unit'] }}</td>
                                    <td>{{ number_format($row['unit_cost'], 2) }} ج.م</td>
                                    <td>{{ number_format($row['stock_value'], 2) }} ج.م</td>
                                </tr>
                            @empty
                                <tr><td colspan="5">لا توجد أرصدة منتجات مُحضّرة</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.table-card>

            <x-admin.table-card
                heading="ملخص حسب الموقع"
                description="إجمالي الإنتاج والتكلفة على مستوى كل موقع."
                :count="collect($batches['by_location'] ?? [])->count()"
            >
                <div class="admin-table-scroll">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>الموقع</th>
                                <th>عدد الدفعات</th>
                                <th>إجمالي الناتج</th>
                                <th>إجمالي الفاقد</th>
                                <th>تكلفة المدخلات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($batches['by_location'] ?? [] as $row)
                                <tr>
                                    <td>{{ $row['location_name'] }}</td>
                                    <td>{{ number_format($row['batches_count']) }}</td>
                                    <td>{{ number_format($row['total_output_quantity'], 3) }}</td>
                                    <td>{{ number_format($row['total_waste_quantity'], 3) }}</td>
                                    <td>{{ number_format($row['total_input_cost'], 2) }} ج.م</td>
                                </tr>
                            @empty
                                <tr><td colspan="5">لا توجد بيانات مواقع</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.table-card>
        </div>

        <x-admin.table-card
            heading="دفعات الإنتاج التفصيلية"
            description="كل دفعة مكتملة مع تكلفة التشغيلة وفرق العائد."
            :count="collect($batches['entries'] ?? [])->count()"
        >
            <div class="admin-table-scroll">
                <table class="admin-data-table">
                    <thead>
                        <tr>
                            <th>رقم الدفعة</th>
                            <th>المنتج المُحضّر</th>
                            <th>الموقع</th>
                            <th>الوصفة</th>
                            <th>الناتج الفعلي</th>
                            <th>الفاقد</th>
                            <th>تكلفة المدخلات</th>
                            <th>تكلفة الوحدة</th>
                            <th>فرق العائد</th>
                            <th>التوقيت</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($batches['entries'] ?? [] as $batch)
                            <tr>
                                <td>{{ $batch->batch_number }}</td>
                                <td>{{ $batch->preparedItem?->name ?? '—' }}</td>
                                <td>{{ $batch->location?->name ?? '—' }}</td>
                                <td>{{ $batch->productionRecipe?->name ?? '—' }}</td>
                                <td>{{ number_format((float) $batch->actual_output_quantity, 3) }} {{ $batch->output_unit }}</td>
                                <td>{{ (float) $batch->waste_quantity > 0 ? number_format((float) $batch->waste_quantity, 3) . ' ' . $batch->output_unit : '—' }}</td>
                                <td>{{ number_format((float) $batch->total_input_cost, 2) }} ج.م</td>
                                <td>{{ number_format((float) $batch->unit_cost, 2) }} ج.م</td>
                                <td>
                                    {{ number_format((float) $batch->yield_variance_quantity, 3) }} {{ $batch->output_unit }}
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ number_format((float) $batch->yield_variance_percentage, 2) }}%
                                    </div>
                                </td>
                                <td>{{ \App\Support\BusinessTime::asLocal($batch->produced_at)->format('Y-m-d h:i A') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10">لا توجد دفعات إنتاج في هذه الفترة</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.table-card>
    </div>
</x-filament-panels::page>
