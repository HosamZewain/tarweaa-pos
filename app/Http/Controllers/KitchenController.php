<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class KitchenController extends Controller
{
    /**
     * Display the kitchen active orders screen.
     */
    public function index(): View
    {
        return view('kitchen.index');
    }
}
