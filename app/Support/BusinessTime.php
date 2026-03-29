<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BusinessTime
{
    public static function timezone(): string
    {
        return (string) config('app.business_timezone', 'Africa/Cairo');
    }

    public static function now(): Carbon
    {
        return Carbon::now(static::timezone());
    }

    public static function today(): Carbon
    {
        return static::now()->startOfDay();
    }

    public static function localDateString(Carbon|string|null $value = null): string
    {
        return static::asLocal($value)->toDateString();
    }

    public static function localDateKey(Carbon|string|null $value = null): string
    {
        return static::asLocal($value)->format('Ymd');
    }

    public static function asLocal(Carbon|string|null $value = null): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->setTimezone(static::timezone());
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value, config('app.timezone'))->setTimezone(static::timezone());
        }

        return static::now();
    }

    public static function formatDateTime(Carbon|string|null $value, string $format = 'Y-m-d H:i'): string
    {
        if (blank($value)) {
            return '—';
        }

        return static::asLocal($value)->format($format);
    }

    public static function formatDate(Carbon|string|null $value, string $format = 'Y-m-d'): string
    {
        if (blank($value)) {
            return '—';
        }

        return static::asLocal($value)->format($format);
    }

    public static function utcRangeForLocalDate(Carbon|string $date): array
    {
        $local = static::asLocal($date);

        return [
            $local->copy()->startOfDay()->utc(),
            $local->copy()->endOfDay()->utc(),
        ];
    }

    public static function utcRangeForLocalMonth(Carbon|string|null $reference = null): array
    {
        $local = static::asLocal($reference);

        return [
            $local->copy()->startOfMonth()->utc(),
            $local->copy()->endOfMonth()->utc(),
        ];
    }

    public static function applyUtcDateRange(
        Builder $query,
        ?string $dateFrom,
        ?string $dateTo,
        string $column = 'created_at',
    ): Builder {
        if ($dateFrom) {
            [$start,] = static::utcRangeForLocalDate($dateFrom);
            $query->where($column, '>=', $start);
        }

        if ($dateTo) {
            [, $end] = static::utcRangeForLocalDate($dateTo);
            $query->where($column, '<=', $end);
        }

        return $query;
    }

    public static function applyUtcDate(Builder $query, Carbon|string $date, string $column = 'created_at'): Builder
    {
        [$start, $end] = static::utcRangeForLocalDate($date);

        return $query->whereBetween($column, [$start, $end]);
    }

    public static function groupByLocalDate(Collection $items, string $dateAttribute = 'created_at'): Collection
    {
        return $items->groupBy(fn ($item) => static::localDateString(data_get($item, $dateAttribute)));
    }
}
