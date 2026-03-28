<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class CounterScreenController extends Controller
{
    public function index(string $lane): View
    {
        abort_unless(in_array($lane, ['odd', 'even'], true), 404);

        return view('counter.index', [
            'lane' => $lane,
        ]);
    }
}
