<div
    x-data="{
        now: new Date(),
        timer: null,
        init() {
            this.timer = setInterval(() => {
                this.now = new Date()
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
            }).format(this.now)
        },
        get timeLabel() {
            return new Intl.DateTimeFormat('ar-EG', {
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true,
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
