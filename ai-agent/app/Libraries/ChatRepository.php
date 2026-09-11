<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

/**
 * Persistence for unauthenticated (guest) conversations.
 *
 * Anonymous conversations use a NULL user_id; authenticated conversations are
 * scoped to the user id stored in the CodeIgniter session.
 */
class ChatRepository
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function listConversations(): array
    {
        $query = $this->db->table('conversations')
            ->select('id, title, created_at, updated_at')
            ->orderBy('updated_at', 'DESC')
            ->orderBy('id', 'DESC');
        $this->applyScope($query);

        return $query->get()->getResultArray();
    }

    public function createConversation(string $title = 'New conversation'): array
    {
        $title = trim($title) ?: 'New conversation';
        $title = mb_substr($title, 0, 255);

        $this->db->table('conversations')->insert([
            'user_id' => $this->currentUserId(),
            'title'   => $title,
        ]);

        $conversation = $this->findConversation((int) $this->db->insertID(), false);
        if (empty($conversation)) {
            throw new RuntimeException('The conversation could not be created.');
        }

        return $conversation;
    }

    public function findConversation(int $id, bool $withMessages = true): ?array
    {
        $query = $this->db->table('conversations')
            ->select('id, title, created_at, updated_at')
            ->where('id', $id);
        $this->applyScope($query);
        $conversation = $query->get()->getRowArray();

        if (empty($conversation)) {
            return null;
        }

        $conversation['id'] = (int) $conversation['id'];
        if ($withMessages) {
            $conversation['messages'] = $this->messages($conversation['id']);
        }

        return $conversation;
    }

    public function messages(int $conversationId): array
    {
        $messages = $this->db->table('messages')
            ->select('id, role, content, model, created_at')
            ->where('conversation_id', $conversationId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(static function (array $message): array {
            $message['id'] = (int) $message['id'];

            return $message;
        }, $messages);
    }

    /**
     * Insert both sides of an exchange as one atomic database operation.
     */
    public function appendExchange(int $conversationId, string $userMessage, string $assistantMessage, ?string $model = null): array
    {
        $conversation = $this->findConversation($conversationId, false);
        if (empty($conversation)) {
            throw new RuntimeException('Conversation not found.');
        }

        $this->db->transStart();
        $messages = $this->db->table('messages');
        $messages->insert([
            'conversation_id' => $conversationId,
            'role'            => 'user',
            'content'         => $userMessage,
        ]);
        $messages->insert([
            'conversation_id' => $conversationId,
            'role'            => 'assistant',
            'content'         => $assistantMessage,
            'model'           => $model,
        ]);
        $conversationUpdate = $this->db->table('conversations')
            ->where('id', $conversationId)
            ->set('updated_at', date('Y-m-d H:i:s'));
        if ($conversation['title'] === 'New conversation') {
            $conversationUpdate->set('title', mb_substr($userMessage, 0, 42));
        }
        $conversationUpdate->update();
        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new RuntimeException('The conversation could not be saved.');
        }

        return $this->findConversation($conversationId);
    }

    public function deleteConversation(int $id): bool
    {
        $conversation = $this->findConversation($id, false);
        if (empty($conversation)) {
            return false;
        }

        $query = $this->db->table('conversations')->where('id', $id);
        $this->applyScope($query);

        return (bool) $query->delete();
    }

    private function currentUserId(): ?int
    {
        $userId = service('session')->get('user_id');

        return is_numeric($userId) && (int) $userId > 0 ? (int) $userId : null;
    }

    private function applyScope(BaseBuilder $query): void
    {
        $userId = $this->currentUserId();
        if ($userId === null) {
            $query->where('user_id IS NULL', null, false);
            return;
        }

        $query->where('user_id', $userId);
    }
}
