<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\ProductRepository;

final class ProductController
{
    public function __construct(private readonly ProductRepository $products = new ProductRepository())
    {
    }

    public function index(Request $request): void
    {
        $limit = max(1, min(500, (int) ($request->query['limit'] ?? 100)));

        Response::json([
            'status' => 'success',
            'data' => $this->products->list($limit),
        ]);
    }

    public function store(Request $request): void
    {
        if (!is_array($request->body)) {
            Response::json(['status' => 'error', 'message' => 'Invalid JSON body'], 422);
            return;
        }

        if (empty($request->body['name'])) {
            Response::json([
                'status' => 'error',
                'message' => 'name is required',
            ], 422);
            return;
        }

        $created = $this->products->create($request->body);

        Response::json([
            'status' => 'success',
            'message' => 'Product created successfully',
            'data' => $created,
        ], 201);
    }

    public function update(Request $request): void
    {
        $id = trim((string) $request->input('id', ''));
        if ($id === '') {
            Response::json(['status' => 'error', 'message' => 'id is required'], 422);
            return;
        }

        $updated = $this->products->updateById($id, is_array($request->body) ? $request->body : []);
        if (!$updated) {
            Response::json(['status' => 'error', 'message' => 'No changes made or product not found'], 404);
            return;
        }

        Response::json(['status' => 'success', 'message' => 'Product updated']);
    }

    public function delete(Request $request): void
    {
        $id = trim((string) $request->input('id', ''));
        if ($id === '') {
            Response::json(['status' => 'error', 'message' => 'id is required'], 422);
            return;
        }

        $deleted = $this->products->deleteById($id);
        if (!$deleted) {
            Response::json(['status' => 'error', 'message' => 'Product not found'], 404);
            return;
        }

        Response::json(['status' => 'success', 'message' => 'Product deleted']);
    }
}
