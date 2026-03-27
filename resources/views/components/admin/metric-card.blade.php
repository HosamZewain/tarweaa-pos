@props([
    'title',
    'value',
    'hint' => null,
    'tone' => 'primary',
])

<article {{ $attributes->class(['admin-metric-card', "admin-metric-card--{$tone}"]) }}>
    <p class="admin-metric-card__label">{{ $title }}</p>
    <p class="admin-metric-card__value">{{ $value }}</p>

    @if (filled($hint) || ! $slot->isEmpty())
        <div class="admin-metric-card__hint">
            @if (filled($hint))
                <span>{{ $hint }}</span>
            @endif

            @if (! $slot->isEmpty())
                <div>{{ $slot }}</div>
            @endif
        </div>
    @endif
</article>
