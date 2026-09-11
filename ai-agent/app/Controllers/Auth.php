<?php

namespace App\Controllers;

use CodeIgniter\HTTP\Exceptions\HTTPException;
use Config\Database;
use Throwable;

class Auth extends BaseController
{
    public function current()
    {
        $user = $this->currentUser();

        return $this->response->setJSON([
            'success' => true,
            'user'    => $user,
        ]);
    }

    public function register()
    {
        $payload = $this->jsonPayload();
        if ($payload === null) {
            return $this->jsonError('Request body must be valid JSON.', 400);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');

        if ($name === '' || mb_strlen($name) > 120) {
            return $this->jsonError('Name is required and must be 120 characters or fewer.', 422);
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            return $this->jsonError('Enter a valid email address.', 422);
        }
        if (mb_strlen($password) < 8) {
            return $this->jsonError('Password must be at least 8 characters.', 422);
        }

        try {
            $db = Database::connect();
            if ($db->table('users')->where('email', $email)->countAllResults() > 0) {
                return $this->jsonError('An account with that email already exists.', 409);
            }

            $db->table('users')->insert([
                'name'          => $name,
                'email'         => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $user = $this->userById((int) $db->insertID());
            service('session')->regenerate(true);
            service('session')->set('user_id', $user['id']);

            return $this->response->setStatusCode(201)->setJSON([
                'success' => true,
                'user'    => $user,
            ]);
        } catch (Throwable) {
            return $this->jsonError('The account could not be created.', 503);
        }
    }

    public function login()
    {
        $payload = $this->jsonPayload();
        if ($payload === null) {
            return $this->jsonError('Request body must be valid JSON.', 400);
        }

        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');

        try {
            $user = Database::connect()->table('users')
                ->select('id, name, email, password_hash')
                ->where('email', $email)
                ->get()
                ->getRowArray();

            if (empty($user) || ! password_verify($password, $user['password_hash'])) {
                return $this->jsonError('Email or password is incorrect.', 401);
            }

            service('session')->regenerate(true);
            service('session')->set('user_id', (int) $user['id']);

            return $this->response->setJSON([
                'success' => true,
                'user'    => $this->publicUser($user),
            ]);
        } catch (Throwable) {
            return $this->jsonError('The account could not be signed in.', 503);
        }
    }

    public function logout()
    {
        service('session')->remove('user_id');
        service('session')->regenerate(true);

        return $this->response->setJSON(['success' => true]);
    }

    private function currentUser(): ?array
    {
        $userId = service('session')->get('user_id');
        if (! is_numeric($userId) || (int) $userId < 1) {
            return null;
        }

        try {
            return $this->userById((int) $userId);
        } catch (Throwable) {
            return null;
        }
    }

    private function userById(int $id): array
    {
        $user = Database::connect()->table('users')
            ->select('id, name, email')
            ->where('id', $id)
            ->get()
            ->getRowArray();

        if (empty($user)) {
            throw new \RuntimeException('User not found.');
        }

        return $this->publicUser($user);
    }

    private function publicUser(array $user): array
    {
        return [
            'id'    => (int) $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
        ];
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
