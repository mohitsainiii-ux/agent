<?php

namespace App\Controllers;

use App\Libraries\PythonApiClient;
use App\Libraries\SettingsStore;
use CodeIgniter\HTTP\Exceptions\HTTPException;

class Chat extends BaseController
{
    public function index()
    {
        return view('chat', [
            'settings' => (new SettingsStore())->get(),
        ]);
    }

    public function send()
    {
        $settings = (new SettingsStore())->get();

        if (empty($settings['python']['enabled'])) {
            return $this->response
                ->setStatusCode(503)
                ->setJSON([
                    'success' => false,
                    'error'   => 'Python processing is disabled. Enable it in Settings.',
                ]);
        }

        try {
            $payload = $this->request->getJSON(true) ?? [];
        } catch (HTTPException) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'success' => false,
                    'error'   => 'Request body must be valid JSON.',
                ]);
        }
        $message = trim((string) ($payload['message'] ?? ''));

        if ($message === '') {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'success' => false,
                    'error'   => 'Message cannot be empty.',
                ]);
        }

        $result = (new PythonApiClient())->chat(
            $settings['python']['api_url'],
            (int) $settings['python']['timeout'],
            ['message' => $message]
        );

        return $this->response
            ->setStatusCode(! empty($result['success']) ? 200 : 502)
            ->setJSON($result);
    }
}