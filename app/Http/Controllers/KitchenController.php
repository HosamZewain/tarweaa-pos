<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KitchenController extends Controller
{
    /**
     * Display the kitchen active orders screen.
     */
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('view_kitchen'), 403);
        
        return view('kitchen.index');
    }
}
