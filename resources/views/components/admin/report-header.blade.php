@props([
    'eyebrow' => 'التقارير',
    'title',
    'description' => null,
    'from' => null,
    'to' => null,
    'meta' => [],
])

@php
    $metaItems = collect($meta)->filter()->values();

    if ($from || $to) {
        $metaItems = $metaItems->prepend('الفترة: ' . ($from ?: '—') . ' ← ' . ($to ?: '—'));
    }
@endphp

<section {{ $attributes->class('admin-page-hero') }}>
    <div class="admin-page-hero__grid">
        <div>
            <span class="admin-page-hero__eyebrow">{{ $eyebrow }}</span>
            <h2 class="admin-page-hero__title">{{ $title }}</h2>

            @if (filled($description))
                <p class="admin-page-hero__description">{{ $description }}</p>
            @endif
        </div>

        @if ($metaItems->isNotEmpty())
            <div class="admin-page-hero__meta lg:justify-end">
                @foreach ($metaItems as $item)
                    <span class="admin-page-hero__meta-item">{{ $item }}</span>
                @endforeach
            </div>
        @endif
    </div>
</section>
