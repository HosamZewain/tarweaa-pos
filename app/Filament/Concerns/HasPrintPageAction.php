<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Illuminate\Support\Js;

trait HasPrintPageAction
{
    protected function makePrintPageAction(string $name = 'printPage', string $label = 'طباعة', ?string $selector = null): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->alpineClickHandler($this->printPageActionScript($selector));
    }

    protected function printPageActionScript(?string $selector = null): string
    {
        return 'if (window.adminPrintPage) { window.adminPrintPage({ selector: ' . Js::from($selector) . ', trigger: $el }) }';
    }
}
