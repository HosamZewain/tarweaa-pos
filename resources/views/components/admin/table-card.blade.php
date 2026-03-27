@props([
    'heading',
    'description' => null,
    'count' => null,
])

<section {{ $attributes->class('admin-table-card') }}>
    <div class="admin-table-card__header">
        <div>
            <h3 class="admin-table-card__title">{{ $heading }}</h3>

            @if (filled($description))
                <p class="admin-table-card__description">{{ $description }}</p>
            @endif
        </div>

        @if (filled($count))
            <span class="admin-pill">
                {{ is_numeric($count) ? number_format((float) $count) : $count }}
            </span>
        @endif
    </div>

    <div class="admin-table-card__body">
        {{ $slot }}
    </div>
</section>
