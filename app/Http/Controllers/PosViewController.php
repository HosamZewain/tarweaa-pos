<?php

namespace App\Http\Controllers;

class PosViewController extends Controller
{
    public function login()
    {
        return view('pos.login');
    }

    public function drawerOpen()
    {
        return view('pos.drawer-open');
    }

    public function pos()
    {
        return view('pos.pos');
    }

    public function drawerClose()
    {
        return view('pos.drawer-close');
    }
}
