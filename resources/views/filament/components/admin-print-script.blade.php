@php
    $printStyles = <<<'CSS'
@page {
    size: A4 portrait;
    margin: 8mm;
}

html, body {
    background: #ffffff !important;
    color: #111827 !important;
    font-family: "Tahoma", "Arial", sans-serif !important;
    font-size: 13px !important;
    line-height: 1.45 !important;
}

body {
    margin: 0 !important;
    padding: 0 !important;
}

#admin-print-stage {
    display: none;
}

.admin-print-root,
.admin-print-root * {
    box-shadow: none !important;
}

.admin-print-root .fi-sidebar,
.admin-print-root .fi-topbar,
.admin-print-root nav,
.admin-print-root aside,
.admin-print-root .fi-breadcrumbs,
.admin-print-root .admin-filter-card,
.admin-print-root .fi-ta-header-toolbar,
.admin-print-root .fi-pagination,
.admin-print-root .fi-pagination-overview,
.admin-print-root .fi-dropdown,
.admin-print-root .fi-modal,
.admin-print-root .fi-ac,
.admin-print-root .fi-btn,
.admin-print-root button {
    display: none !important;
}

.admin-print-root,
.admin-print-root .admin-page-shell,
.admin-print-root .fi-page,
.admin-print-root .fi-section,
.admin-print-root .fi-in,
.admin-print-root .fi-ta,
.admin-print-root .fi-simple-layout,
.admin-print-root [class*="fi-width-"] {
    width: 100% !important;
    max-width: none !important;
    margin: 0 !important;
    padding: 0 !important;
}

.admin-print-root .admin-page-shell {
    display: block !important;
}

.admin-print-root .admin-metric-grid {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    gap: 10px !important;
    margin-bottom: 14px !important;
}

.admin-print-root .admin-metric-grid.xl\:grid-cols-6,
.admin-print-root .admin-metric-grid.xl\:grid-cols-4 {
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
}

.admin-print-root .admin-table-card,
.admin-print-root .admin-metric-card,
.admin-print-root .fi-section,
.admin-print-root .fi-in {
    border: 1px solid #d1d5db !important;
    border-radius: 8px !important;
    background: #ffffff !important;
    color: #111827 !important;
    break-inside: avoid !important;
    page-break-inside: avoid !important;
    margin-bottom: 12px !important;
}

.admin-print-root table,
.admin-print-root .fi-ta-table table {
    width: 100% !important;
    border-collapse: collapse !important;
    table-layout: auto !important;
}

.admin-print-root th,
.admin-print-root td,
.admin-print-root .fi-ta-cell,
.admin-print-root .fi-ta-header-cell {
    border: 1px solid #d1d5db !important;
    padding: 6px 8px !important;
    vertical-align: top !important;
    text-align: right !important;
    color: #111827 !important;
    background: #ffffff !important;
    font-size: 12px !important;
}

.admin-print-root thead th,
.admin-print-root tfoot td {
    background: #f3f4f6 !important;
    font-weight: 700 !important;
}

.admin-print-root .admin-table-scroll,
.admin-print-root .fi-ta-content {
    overflow: visible !important;
}

.admin-print-root a {
    color: #111827 !important;
    text-decoration: none !important;
}

.admin-print-root .admin-progress {
    border: 1px solid #d1d5db !important;
    background: #f3f4f6 !important;
}

.admin-print-root .admin-progress span {
    background: #6b7280 !important;
}

.admin-print-root .text-success-600,
.admin-print-root .text-danger-600,
.admin-print-root .text-warning-600,
.admin-print-root .text-info-600,
.admin-print-root .dark\:text-success-400,
.admin-print-root .dark\:text-danger-400,
.admin-print-root .dark\:text-warning-400,
.admin-print-root .dark\:text-info-400 {
    color: #111827 !important;
}

.admin-print-root .font-bold,
.admin-print-root .font-semibold {
    font-weight: 700 !important;
}

.admin-print-root .admin-empty-state,
.admin-print-root .fi-no-records {
    border: 1px dashed #d1d5db !important;
    padding: 12px !important;
}

.admin-print-root .fi-ta-record {
    break-inside: avoid !important;
    page-break-inside: avoid !important;
}

@media print {
    html.admin-print-active,
    body.admin-print-active {
        background: #ffffff !important;
        overflow: visible !important;
    }

    body.admin-print-active > * {
        display: none !important;
    }

    body.admin-print-active #admin-print-stage {
        display: block !important;
        position: static !important;
        inset: auto !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #ffffff !important;
    }
}
CSS;
@endphp

<style data-no-print>
    {!! $printStyles !!}
</style>

<script>
    if (! window.adminPrintPage) {
        window.adminPrintPage = function (options) {
        options = options || {}
        var trigger = options.trigger || null

        var selectors = [
            options.selector,
            '[data-admin-print-root]',
            '.admin-page-shell',
            '.fi-main .fi-page',
            '.fi-page',
        ].filter(Boolean)

        var source = null

        if (trigger && trigger.closest) {
            source = trigger.closest('.admin-page-shell')
                || trigger.closest('.fi-page')
                || trigger.closest('main.fi-main')
                || trigger.closest('main')
        }

        if (! source) {
            for (var index = 0; index < selectors.length; index++) {
                var currentSelector = selectors[index]

                source = document.querySelector(currentSelector)

                if (source) {
                    break
                }
            }
        }

        if (! source) {
            source = document.querySelector('main') || document.body
        }

        var printable = source.cloneNode(true)
        var printStage = document.getElementById('admin-print-stage')

        printable.querySelectorAll('script, template').forEach(function (element) {
            element.remove()
        })
        printable.querySelectorAll([
            '.fi-page-header-actions',
            '.fi-header-actions',
            '.fi-breadcrumbs',
            '.fi-ta-header-toolbar',
            '.fi-pagination',
            '.fi-pagination-overview',
            '.fi-input-wrp-trailing',
            '.fi-dropdown',
            '.fi-modal',
            '.admin-filter-card__actions',
            '[data-no-print]',
            'button',
        ].join(',')).forEach(function (element) {
            element.remove()
        })

        if (! printStage) {
            printStage = document.createElement('div')
            printStage.id = 'admin-print-stage'
            document.body.appendChild(printStage)
        }

        printStage.innerHTML = ''

        var root = document.createElement('div')
        root.className = 'admin-print-root'
        root.appendChild(printable)
        printStage.appendChild(root)

        var cleanup = function () {
            document.documentElement.classList.remove('admin-print-active')
            document.body.classList.remove('admin-print-active')
            printStage.innerHTML = ''
        }

        window.onafterprint = cleanup

        document.documentElement.classList.add('admin-print-active')
        document.body.classList.add('admin-print-active')

        window.setTimeout(function () {
            window.print()

            window.setTimeout(function () {
                cleanup()
            }, 300)
        }, 50)
        }
    }
</script>
