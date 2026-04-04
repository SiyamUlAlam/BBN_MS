<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\PackageRepository;

final class PackageController
{
    public function __construct(private readonly PackageRepository $packages = new PackageRepository())
    {
    }

    public function index(Request $request): void
    {
        Response::json([
            'status' => 'success',
            'data' => $this->packages->list(),
        ]);
    }

    public function store(Request $request): void
    {
        if (!is_array($request->body) || empty($request->body['name'])) {
            Response::json(['status' => 'error', 'message' => 'name is required'], 422);
            return;
        }

        $created = $this->packages->create($request->body);
        Response::json(['status' => 'success', 'data' => $created], 201);
    }

    public function update(Request $request): void
    {
        $id = trim((string) $request->input('id', ''));
        if ($id === '') {
            Response::json(['status' => 'error', 'message' => 'id is required'], 422);
            return;
        }

        $updated = $this->packages->updateById($id, is_array($request->body) ? $request->body : []);
        if (!$updated) {
            Response::json(['status' => 'error', 'message' => 'No changes made or package not found'], 404);
            return;
        }

        Response::json(['status' => 'success', 'message' => 'Package updated']);
    }

    public function delete(Request $request): void
    {
        $id = trim((string) $request->input('id', ''));
        if ($id === '') {
            Response::json(['status' => 'error', 'message' => 'id is required'], 422);
            return;
        }

        $deleted = $this->packages->deleteById($id);
        if (!$deleted) {
            Response::json(['status' => 'error', 'message' => 'Package not found'], 404);
            return;
        }

        Response::json(['status' => 'success', 'message' => 'Package deleted']);
    }
}
