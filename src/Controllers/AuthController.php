<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\AuthService;

final class AuthController
{
    public function __construct(private readonly AuthService $auth = new AuthService())
    {
    }

    public function loginPage(Request $request): void
    {
        if ($this->auth->isAuthenticated()) {
            Response::redirect('/');
            return;
        }

        $error = (string) ($request->query['error'] ?? '');
        $errorHtml = $error !== '' ? '<p style="color:#b91c1c;">Invalid login credentials.</p>' : '';

        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" /><title>Login - BBN ISP</title><style>body{margin:0;font-family:Tahoma,sans-serif;background:linear-gradient(120deg,#eff6ff,#f5fdf4)}.wrap{min-height:100vh;display:grid;place-items:center}.card{width:min(420px,92vw);background:#fff;border:1px solid #dbeafe;border-radius:12px;padding:22px;box-shadow:0 10px 35px rgba(15,23,42,.08)}h1{margin:0 0 6px 0}p{color:#475569}form{display:grid;gap:10px}input,button{padding:10px;border:1px solid #cbd5e1;border-radius:8px}button{background:#1d4ed8;color:#fff;border:none;cursor:pointer}</style></head><body><div class="wrap"><div class="card"><h1>BBN ISP Login</h1><p>Sign in to access admin panel.</p>' . $errorHtml . '<form method="post" action="/login"><input name="username" placeholder="Username" required /><input name="password" type="password" placeholder="Password" required /><button type="submit">Sign In</button></form></div></div></body></html>';
    }

    public function login(Request $request): void
    {
        $body = is_array($request->body) ? $request->body : [];
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($username === '' || $password === '') {
            Response::redirect('/login?error=1');
            return;
        }

        if (!$this->auth->attempt($username, $password)) {
            Response::redirect('/login?error=1');
            return;
        }

        Response::redirect('/');
    }

    public function logout(Request $request): void
    {
        $this->auth->logout();
        Response::redirect('/login');
    }

    public function me(Request $request): void
    {
        Response::json([
            'status' => 'success',
            'data' => $this->auth->user(),
        ]);
    }
}
