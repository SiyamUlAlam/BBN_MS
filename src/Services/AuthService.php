<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;

final class AuthService
{
    public function __construct(private readonly UserRepository $users = new UserRepository())
    {
    }

    public function ensureDefaultAdmin(string $username, string $password, string $fullName = 'Administrator'): void
    {
        if ($username === '' || $password === '') {
            return;
        }

        $existing = $this->users->findByUsername($username);
        if ($existing !== null) {
            return;
        }

        $this->users->create([
            'full_name' => $fullName,
            'username' => $username,
            'password' => $password,
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }

    public function attempt(string $username, string $password): bool
    {
        $user = $this->users->findByUsername($username);
        if ($user === null) {
            return false;
        }

        $hash = (string) ($user['password_hash'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            return false;
        }

        $_SESSION['auth'] = [
            'id' => $user['_id'] ?? null,
            'username' => $user['username'] ?? null,
            'full_name' => $user['full_name'] ?? null,
            'role' => $user['role'] ?? null,
            'logged_in_at' => date(DATE_ATOM),
        ];

        return true;
    }

    public function isAuthenticated(): bool
    {
        return isset($_SESSION['auth']) && is_array($_SESSION['auth']);
    }

    public function user(): ?array
    {
        return $this->isAuthenticated() ? (array) $_SESSION['auth'] : null;
    }

    public function logout(): void
    {
        unset($_SESSION['auth']);
    }
}
