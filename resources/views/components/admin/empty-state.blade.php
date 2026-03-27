@props([
    'title' => 'لا توجد بيانات',
    'description' => 'لا توجد نتائج متاحة في الوقت الحالي لهذه الفترة أو الفلاتر المحددة.',
])

<div {{ $attributes->class('admin-empty-state') }}>
    <div class="admin-badge admin-badge--neutral">لا توجد نتائج</div>
    <h4 class="admin-empty-state__title">{{ $title }}</h4>
    <p class="admin-empty-state__description">{{ $description }}</p>
</div>
