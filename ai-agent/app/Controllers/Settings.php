<?php

namespace App\Controllers;

use App\Libraries\PythonApiClient;
use App\Libraries\SettingsStore;

class Settings extends BaseController
{
    public function index()
    {
        return $this->response->setJSON([
            'success'  => true,
            'settings' => (new SettingsStore())->get(),
        ]);
    }

    public function save()
    {
        $payload  = $this->request->getJSON(true) ?? [];
        $settings = (new SettingsStore())->save($payload);

        return $this->response->setJSON([
            'success'  => true,
            'settings' => $settings,
            'message'  => 'Settings saved.',
        ]);
    }

    public function test()
    {
        $store    = new SettingsStore();
        $settings = $store->get();
        $incoming = $this->request->getJSON(true) ?? [];

        $url     = trim((string) ($incoming['api_url'] ?? $settings['python']['api_url']));
        $timeout = (int) ($incoming['timeout'] ?? $settings['python']['timeout']);

        $result = (new PythonApiClient())->health($url, max(1, $timeout));

        return $this->response->setJSON($result);
    }
}
