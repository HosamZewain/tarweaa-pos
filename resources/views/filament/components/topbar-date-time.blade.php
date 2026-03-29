@php
    $serverNow = \App\Support\BusinessTime::now();
@endphp

<div
    x-data="{
        now: new Date(@js($serverNow->toIso8601String())),
        timer: null,
        init() {
            this.timer = setInterval(() => {
                this.now = new Date(this.now.getTime() + 1000)
            }, 1000)
        },
        destroy() {
            if (this.timer) {
                clearInterval(this.timer)
            }
        },
        get dateLabel() {
            return new Intl.DateTimeFormat('ar-EG', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
                timeZone: @js(\App\Support\BusinessTime::timezone()),
            }).format(this.now)
        },
        get timeLabel() {
            return new Intl.DateTimeFormat('ar-EG', {
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true,
                timeZone: @js(\App\Support\BusinessTime::timezone()),
            }).format(this.now)
        },
    }"
    class="admin-topbar-date-time"
>
    <div class="admin-topbar-date-time__label">
        التاريخ والوقت
    </div>

    <div class="admin-topbar-date-time__value">
        <span class="admin-topbar-date-time__date" x-text="dateLabel"></span>
        <span class="admin-topbar-date-time__separator">•</span>
        <span class="admin-topbar-date-time__time" x-text="timeLabel"></span>
    </div>
</div>
