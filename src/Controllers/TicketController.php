<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\TicketRepository;

final class TicketController
{
    public function __construct(private readonly TicketRepository $tickets = new TicketRepository())
    {
    }

    public function index(Request $request): void
    {
        Response::json([
            'status' => 'success',
            'data' => $this->tickets->list(),
        ]);
    }

    public function store(Request $request): void
    {
        if (!is_array($request->body) || empty($request->body['subject'])) {
            Response::json(['status' => 'error', 'message' => 'subject is required'], 422);
            return;
        }

        $created = $this->tickets->create($request->body);
        Response::json(['status' => 'success', 'data' => $created], 201);
    }

    public function updateStatus(Request $request): void
    {
        if (!is_array($request->body) || empty($request->body['id']) || empty($request->body['status'])) {
            Response::json(['status' => 'error', 'message' => 'id and status are required'], 422);
            return;
        }

        $ok = $this->tickets->updateStatus((string) $request->body['id'], (string) $request->body['status']);
        Response::json(['status' => 'success', 'data' => ['updated' => $ok]]);
    }
}
