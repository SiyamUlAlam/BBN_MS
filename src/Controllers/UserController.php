<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\UserRepository;

final class UserController
{
    public function __construct(private readonly UserRepository $users = new UserRepository())
    {
    }

    public function index(Request $request): void
    {
        Response::json([
            'status' => 'success',
            'data' => $this->users->list(),
        ]);
    }

    public function store(Request $request): void
    {
        if (!is_array($request->body) || empty($request->body['username']) || empty($request->body['password'])) {
            Response::json(['status' => 'error', 'message' => 'username and password are required'], 422);
            return;
        }

        $created = $this->users->create($request->body);
        Response::json(['status' => 'success', 'data' => $created], 201);
    }
}
