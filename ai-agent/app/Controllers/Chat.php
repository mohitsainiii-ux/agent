<?php

namespace App\Controllers;

use App\Libraries\SettingsStore;

class Chat extends BaseController
{
    public function index()
    {
        return view('chat', [
            'settings' => (new SettingsStore())->get(),
        ]);
    }
}