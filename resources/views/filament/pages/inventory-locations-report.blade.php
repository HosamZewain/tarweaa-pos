<x-filament-panels::page>
    @php
        $valuation = $reportData['valuation'] ?? ['rows' => collect(), 'summary' => []];
        $stockRows = collect($reportData['stock_rows'] ?? []);
        $lowStockRows = collect($reportData['low_stock_rows'] ?? []);
        $purchases = $reportData['purchases'] ?? ['entries' => collect(), 'by_location' => collect(), 'summary' => []];
        $received = $reportData['received'] ?? ['entries' => collect(), 'by_location' => collect(), 'summary' => []];
        $transfers = $reportData['transfers'] ?? ['entries' => collect(), 'by_status' => collect(), 'summary' => []];
        $reconciliation = $reportData['reconciliation'] ?? ['rows' => collect(), 'variance_rows' => collect(), 'summary' => []];
    @endphp

    <div class="admin-page-shell">
            <x-admin.report-header
                title="تقرير المخزون متعدد المواقع"
                description="متابعة الأرصدة والحركات والمشتريات والتحويلات على مستوى كل موقع مخزني."
                :from="$date_from"
                :to="$date_to"
                :meta="['يبقي التقارير القديمة كما هي', 'يفصل بين أوامر الشراء والاستلام الفعلي', 'يشمل مطابقة الرصيد العام مع مجموع المواقع']"
            />

        <div class="admin-filter-card">
            <div class="admin-filter-card__header">
                <div>
                    <h3 class="admin-filter-card__title">فلاتر التقرير</h3>
                    <p class="admin-filter-card__description">يمكنك قصر التقرير على موقع واحد أو مراجعة كل المواقع معًا.</p>
                </div>

                <x-admin.badge tone="info">متعدد المواقع</x-admin.badge>
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
                title="إجمالي قيمة المخزون"
                :value="number_format($valuation['summary']['total_value'] ?? 0, 2) . ' ج.م'"
                hint="على مستوى المواقع المختارة"
                tone="primary"
            />
            <x-admin.metric-card
                title="إجمالي الكميات"
                :value="number_format($valuation['summary']['total_items'] ?? 0, 3)"
                hint="مجموع أرصدة المواقع"
                tone="info"
            />
            <x-admin.metric-card
                title="مواد منخفضة"
                :value="number_format($lowStockRows->count())"
                hint="حسب الحد الأدنى لكل موقع"
                tone="danger"
            />
            <x-admin.metric-card
                title="تحويلات الفترة"
                :value="number_format($transfers['summary']['transfers_count'] ?? 0)"
                hint="تشمل المرسل والمستلم"
                tone="warning"
            />
            <x-admin.metric-card
                title="استلامات فعلية"
                :value="number_format($received['summary']['received_quantity'] ?? 0, 3)"
                hint="من حركة الشراء الفعلية داخل المواقع"
                tone="success"
            />
            <x-admin.metric-card
                title="مواد غير متطابقة"
                :value="number_format($reconciliation['summary']['mismatched_items'] ?? 0)"
                hint="فرق بين الرصيد العام ومجموع المواقع"
                tone="danger"
            />
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-admin.table-card
                heading="القيمة حسب الموقع"
                description="إجمالي القيمة والكميات داخل كل موقع."
                :count="collect($valuation['rows'] ?? [])->count()"
            >
                <div class="admin-table-scroll">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>الموقع</th>
                                <th>النوع</th>
                                <th>عدد المواد</th>
                                <th>إجمالي الكميات</th>
                                <th>القيمة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($valuation['rows'] ?? [] as $row)
                                <tr>
                                    <td>{{ $row['location_name'] }}</td>
                                    <td>{{ $row['location_type'] }}</td>
                                    <td>{{ number_format($row['item_count']) }}</td>
                                    <td>{{ number_format($row['total_items'], 3) }}</td>
                                    <td class="font-semibold">{{ number_format($row['total_value'], 2) }} ج.م</td>
                                </tr>
                            @empty
                                <tr><td colspan="5">لا توجد بيانات</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.table-card>

            <x-admin.table-card
                heading="ملخص المشتريات حسب الموقع"
                description="هذه بيانات أوامر الشراء الموجهة للمواقع، وليست الاستلام الفعلي."
                :count="collect($purchases['by_location'] ?? [])->count()"
            >
                <div class="admin-table-scroll">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>الموقع</th>
                                <th>عدد الأوامر</th>
                                <th>المستلم</th>
                                <th>الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($purchases['by_location'] ?? [] as $row)
                                <tr>
                                    <td>{{ $row['location_name'] }}</td>
                                    <td>{{ number_format($row['purchases_count']) }}</td>
                                    <td>{{ number_format($row['received_count']) }}</td>
                                    <td class="font-semibold">{{ number_format($row['total_amount'], 2) }} ج.م</td>
                                </tr>
                            @empty
                                <tr><td colspan="4">لا توجد مشتريات</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.table-card>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-admin.table-card
                heading="الاستلام الفعلي حسب الموقع"
                description="يعتمد على حركات الاستلام الفعلية المسجلة في المخزون."
                :count="collect($received['by_location'] ?? [])->count()"
            >
                <div class="admin-table-scroll">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>الموقع</th>
                                <th>عدد حركات الاستلام</th>
                                <th>الكمية المستلمة</th>
                                <th>القيمة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($received['by_location'] ?? [] as $row)
                                <tr>
                                    <td>{{ $row['location_name'] }}</td>
                                    <td>{{ number_format($row['transactions_count']) }}</td>
                                    <td>{{ number_format($row['received_quantity'], 3) }}</td>
                                    <td class="font-semibold">{{ number_format($row['received_value'], 2) }} ج.م</td>
                                </tr>
                            @empty
                                <tr><td colspan="4">لا توجد استلامات فعلية</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.table-card>

            <x-admin.table-card
                heading="مطابقة الرصيد العام"
                description="تقارن الرصيد العام لكل مادة مع مجموع أرصدة المواقع كلها."
                :count="collect($reconciliation['variance_rows'] ?? [])->count()"
            >
                <div class="admin-table-scroll">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>المادة</th>
                                <th>الرصيد العام</th>
                                <th>مجموع المواقع</th>
                                <th>الفرق</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse (($reconciliation['variance_rows'] ?? collect()) as $row)
                                <tr>
                                    <td>{{ $row['item_name'] }}</td>
                                    <td>{{ number_format($row['global_stock'], 3) }} {{ $row['unit'] }}</td>
                                    <td>{{ number_format($row['locations_total_stock'], 3) }} {{ $row['unit'] }}</td>
                                    <td class="font-semibold text-danger-600 dark:text-danger-400">{{ number_format($row['variance'], 3) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4">لا توجد فروقات بين الرصيد العام ومجموع المواقع</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.table-card>
        </div>

        <x-admin.table-card
            heading="المخزون حسب الموقع"
            description="الرصيد الحالي والتكلفة والقيمة لكل مادة داخل كل موقع."
            :count="$stockRows->count()"
        >
            <div class="admin-table-scroll">
                <table class="admin-data-table">
                    <thead>
                        <tr>
                            <th>الموقع</th>
                            <th>المادة</th>
                            <th>التصنيف</th>
                            <th>الرصيد</th>
                            <th>الحد الأدنى</th>
                            <th>تكلفة الوحدة</th>
                            <th>القيمة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stockRows as $row)
                            <tr>
                                <td>{{ $row['location_name'] }}</td>
                                <td>
                                    <div class="font-semibold">{{ $row['item_name'] }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row['item_sku'] ?: '—' }}</div>
                                </td>
                                <td>{{ $row['category'] ?: '—' }}</td>
                                <td>{{ number_format($row['current_stock'], 3) }} {{ $row['unit'] }}</td>
                                <td>{{ number_format($row['minimum_stock'], 3) }}</td>
                                <td>{{ number_format($row['unit_cost'], 2) }} ج.م</td>
                                <td class="font-semibold">{{ number_format($row['stock_value'], 2) }} ج.م</td>
                            </tr>
                        @empty
                            <tr><td colspan="7">لا توجد أرصدة مواقع</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.table-card>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-admin.table-card
                heading="مواد منخفضة حسب الموقع"
                description="المواد التي وصلت أو هبطت عن الحد الأدنى داخل كل موقع."
                :count="$lowStockRows->count()"
            >
                <div class="admin-table-scroll">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>الموقع</th>
                                <th>المادة</th>
                                <th>الرصيد</th>
                                <th>الحد الأدنى</th>
                                <th>العجز</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($lowStockRows as $row)
                                <tr>
                                    <td>{{ $row['location_name'] }}</td>
                                    <td>{{ $row['item_name'] }}</td>
                                    <td>{{ number_format($row['current_stock'], 3) }} {{ $row['unit'] }}</td>
                                    <td>{{ number_format($row['minimum_stock'], 3) }}</td>
                                    <td class="font-semibold text-danger-600 dark:text-danger-400">{{ number_format($row['deficit'], 3) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5">لا توجد مواد منخفضة</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.table-card>

            <x-admin.table-card
                heading="ملخص التحويلات"
                description="عدد وكميات التحويلات حسب الحالة."
                :count="collect($transfers['by_status'] ?? [])->count()"
            >
                <div class="admin-table-scroll">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>الحالة</th>
                                <th>عدد التحويلات</th>
                                <th>عدد البنود</th>
                                <th>مرسل</th>
                                <th>مستلم</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($transfers['by_status'] ?? [] as $row)
                                <tr>
                                    <td>{{ $row['status'] }}</td>
                                    <td>{{ number_format($row['transfers_count']) }}</td>
                                    <td>{{ number_format($row['items_count']) }}</td>
                                    <td>{{ number_format($row['quantity_sent'], 3) }}</td>
                                    <td>{{ number_format($row['quantity_received'], 3) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5">لا توجد تحويلات</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.table-card>
        </div>

        <x-admin.table-card
            heading="تفاصيل الاستلام الفعلي"
            description="كل حركة استلام فعلية مرتبطة بالموقع والمادة."
            :count="collect($received['entries'] ?? [])->count()"
        >
            <div class="admin-table-scroll">
                <table class="admin-data-table">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>الموقع</th>
                            <th>المادة</th>
                            <th>الكمية</th>
                            <th>تكلفة الوحدة</th>
                            <th>القيمة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($received['entries'] ?? collect()) as $entry)
                            <tr>
                                <td>{{ $entry->created_at?->timezone(\App\Support\BusinessTime::timezone())->format('Y-m-d h:i A') }}</td>
                                <td>{{ $entry->inventoryLocation?->name ?? '—' }}</td>
                                <td>{{ $entry->inventoryItem?->name ?? '—' }}</td>
                                <td>{{ number_format($entry->quantity, 3) }} {{ $entry->inventoryItem?->unit }}</td>
                                <td>{{ number_format($entry->unit_cost, 2) }} ج.م</td>
                                <td>{{ number_format($entry->total_cost, 2) }} ج.م</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">لا توجد حركات استلام في الفترة الحالية</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.table-card>

        <x-admin.table-card
            heading="تفاصيل التحويلات"
            description="كل تحويل بين المواقع مع الحالة والمستخدمين المسؤولين."
            :count="collect($transfers['entries'] ?? [])->count()"
        >
            <div class="admin-table-scroll">
                <table class="admin-data-table">
                    <thead>
                        <tr>
                            <th>رقم التحويل</th>
                            <th>من</th>
                            <th>إلى</th>
                            <th>الحالة</th>
                            <th>مطلوب بواسطة</th>
                            <th>نقل بواسطة</th>
                            <th>استلم بواسطة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($transfers['entries'] ?? collect()) as $transfer)
                            <tr>
                                <td>{{ $transfer->transfer_number }}</td>
                                <td>{{ $transfer->sourceLocation?->name ?? '—' }}</td>
                                <td>{{ $transfer->destinationLocation?->name ?? '—' }}</td>
                                <td>{{ $transfer->status }}</td>
                                <td>{{ $transfer->requester?->name ?? '—' }}</td>
                                <td>{{ $transfer->transferredBy?->name ?? '—' }}</td>
                                <td>{{ $transfer->receivedBy?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7">لا توجد تحويلات في الفترة الحالية</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.table-card>
    </div>
</x-filament-panels::page>
