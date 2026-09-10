<?php

namespace App\Controllers;

use App\Libraries\ChatRepository;
use App\Libraries\PythonApiClient;
use App\Libraries\SettingsStore;
use CodeIgniter\HTTP\Exceptions\HTTPException;
use Throwable;

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
        $payload = $this->jsonPayload();
        if ($payload === null) {
            return $this->jsonError('Request body must be valid JSON.', 400);
        }
        if (trim((string) ($payload['message'] ?? '')) === '') {
            return $this->jsonError('Message cannot be empty.', 400);
        }

        try {
            $conversation = (new ChatRepository())->createConversation();

            return $this->sendMessage((int) $conversation['id'], $payload);
        } catch (Throwable) {
            return $this->jsonError('The conversation could not be created.', 503);
        }
    }

    public function conversations()
    {
        try {
            return $this->response->setJSON([
                'success'       => true,
                'conversations' => (new ChatRepository())->listConversations(),
            ]);
        } catch (Throwable) {
            return $this->jsonError('Conversations are temporarily unavailable.', 503);
        }
    }

    public function createConversation()
    {
        $payload = $this->jsonPayload();
        if ($payload === null) {
            return $this->jsonError('Request body must be valid JSON.', 400);
        }

        try {
            $conversation = (new ChatRepository())->createConversation((string) ($payload['title'] ?? 'New conversation'));

            return $this->response->setStatusCode(201)->setJSON([
                'success'      => true,
                'conversation' => $conversation,
            ]);
        } catch (Throwable) {
            return $this->jsonError('The conversation could not be created.', 503);
        }
    }

    public function loadConversation(int $id)
    {
        try {
            $conversation = (new ChatRepository())->findConversation($id);
            if (empty($conversation)) {
                return $this->jsonError('Conversation not found.', 404);
            }

            return $this->response->setJSON([
                'success'      => true,
                'conversation' => $conversation,
            ]);
        } catch (Throwable) {
            return $this->jsonError('The conversation could not be loaded.', 503);
        }
    }

    public function deleteConversation(int $id)
    {
        try {
            if (! (new ChatRepository())->deleteConversation($id)) {
                return $this->jsonError('Conversation not found.', 404);
            }

            return $this->response->setJSON(['success' => true]);
        } catch (Throwable) {
            return $this->jsonError('The conversation could not be deleted.', 503);
        }
    }

    public function sendMessage(int $conversationId, ?array $payload = null)
    {
        $settings = (new SettingsStore())->get();

        if (empty($settings['python']['enabled'])) {
            return $this->jsonError('Python processing is disabled. Enable it in Settings.', 503);
        }

        $payload ??= $this->jsonPayload();
        if ($payload === null) {
            return $this->jsonError('Request body must be valid JSON.', 400);
        }
        $message = trim((string) ($payload['message'] ?? ''));

        if ($message === '') {
            return $this->jsonError('Message cannot be empty.', 400);
        }

        try {
            $repository = new ChatRepository();
            $conversation = $repository->findConversation($conversationId);
            if (empty($conversation)) {
                return $this->jsonError('Conversation not found.', 404);
            }

            $history = array_map(static fn (array $item): array => [
                'role'    => $item['role'],
                'content' => $item['content'],
            ], array_filter(
                $conversation['messages'] ?? [],
                static fn (array $item): bool => in_array($item['role'], ['user', 'assistant'], true)
            ));
            $history = array_values(array_slice($history, -100));

            $result = (new PythonApiClient())->chat(
                $settings['python']['api_url'],
                (int) $settings['python']['timeout'],
                ['message' => $message, 'history' => $history]
            );

            if (empty($result['success'])) {
                return $this->response->setStatusCode(502)->setJSON($result);
            }
            $responseText = trim((string) ($result['response'] ?? ''));
            if ($responseText === '') {
                return $this->jsonError('The AI service returned an empty response.', 502);
            }

            $saved = $repository->appendExchange(
                $conversationId,
                $message,
                $responseText,
                $result['model'] ?? null
            );

            return $this->response->setJSON([
                'success'      => true,
                'response'     => $responseText,
                'type'         => $result['type'] ?? 'text',
                'conversation' => $saved,
            ]);
        } catch (Throwable) {
            return $this->jsonError('The message could not be saved. Please try again.', 503);
        }
    }

    private function jsonPayload(): ?array
    {
        try {
            $payload = $this->request->getJSON(true);
        } catch (HTTPException) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }

    private function jsonError(string $message, int $status)
    {
        return $this->response->setStatusCode($status)->setJSON([
            'success' => false,
            'error'   => $message,
        ]);
    }
}