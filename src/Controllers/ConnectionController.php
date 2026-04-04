<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\ConnectionRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\FinanceRepository;
use App\Repositories\ProductRepository;
use App\Repositories\TopologyRepository;
use App\Services\ConnectionCostService;

final class ConnectionController
{
    public function __construct(
        private readonly ConnectionRepository $connections = new ConnectionRepository(),
        private readonly ProductRepository $products = new ProductRepository(),
        private readonly ConnectionCostService $costService = new ConnectionCostService(),
        private readonly CustomerRepository $customers = new CustomerRepository(),
        private readonly TopologyRepository $topology = new TopologyRepository(),
        private readonly FinanceRepository $finance = new FinanceRepository(),
    ) {
    }

    public function index(Request $request): void
    {
        Response::json(['status' => 'success', 'data' => $this->connections->list()]);
    }

    public function store(Request $request): void
    {
        if (!is_array($request->body) || empty($request->body['customer_id'])) {
            Response::json(['status' => 'error', 'message' => 'customer_id is required'], 422);
            return;
        }

        $items = is_array($request->body['items'] ?? null) ? $request->body['items'] : [];
        $serviceCharge = (float) ($request->body['service_charge'] ?? 0);
        $costPreview = $this->costService->preview($items, $serviceCharge);

        foreach ($costPreview['items'] as $line) {
            $ok = $this->products->decrementStock((string) $line['product_id'], (int) $line['quantity']);
            if (!$ok) {
                Response::json([
                    'status' => 'error',
                    'message' => 'Insufficient stock for product_id ' . $line['product_id'],
                ], 422);
                return;
            }
        }

        $order = $this->connections->createOrder([
            'customer_id' => (string) $request->body['customer_id'],
            'technician' => $request->body['technician'] ?? null,
            'line_source_id' => $request->body['line_source_id'] ?? null,
            'distribution_box_id' => $request->body['distribution_box_id'] ?? null,
            'service_charge' => $costPreview['service_charge'],
            'products_total' => $costPreview['products_total'],
            'grand_total' => $costPreview['grand_total'],
            'items' => $costPreview['items'],
            'status' => $request->body['status'] ?? 'connected',
            'connected_on' => $request->body['connected_on'] ?? date('Y-m-d'),
        ]);

        if (!empty($request->body['distribution_box_id'])) {
            $this->topology->incrementUsedPort((string) $request->body['distribution_box_id']);
        }

        $this->customers->setStatus((string) $request->body['customer_id'], 'active');

        if ((float) $costPreview['grand_total'] > 0) {
            $this->finance->addIncome([
                'date' => (string) ($order['connected_on'] ?? date('Y-m-d')),
                'source' => 'new_connection',
                'category' => 'connection_charge',
                'amount' => (float) $costPreview['grand_total'],
                'note' => 'Connection charge for ' . (string) $request->body['customer_id'],
            ]);
        }

        Response::json([
            'status' => 'success',
            'message' => 'Connection created successfully',
            'data' => $order,
        ], 201);
    }

    public function update(Request $request): void
    {
        $id = trim((string) $request->input('id', ''));
        if ($id === '') {
            Response::json(['status' => 'error', 'message' => 'id is required'], 422);
            return;
        }

        $updated = $this->connections->updateById($id, is_array($request->body) ? $request->body : []);
        if (!$updated) {
            Response::json(['status' => 'error', 'message' => 'No changes made or connection not found'], 404);
            return;
        }

        Response::json(['status' => 'success', 'message' => 'Connection updated']);
    }

    public function delete(Request $request): void
    {
        $id = trim((string) $request->input('id', ''));
        if ($id === '') {
            Response::json(['status' => 'error', 'message' => 'id is required'], 422);
            return;
        }

        $deleted = $this->connections->deleteById($id);
        if (!$deleted) {
            Response::json(['status' => 'error', 'message' => 'Connection not found'], 404);
            return;
        }

        Response::json(['status' => 'success', 'message' => 'Connection deleted']);
    }

    public function printSummary(Request $request): void
    {
        $id = (string) ($request->query['id'] ?? '');
        $tag = trim((string) ($request->query['tag'] ?? 'Connection Copy'));
        if ($id === '') {
            Response::json(['status' => 'error', 'message' => 'id query param is required'], 422);
            return;
        }

        $order = $this->connections->findById($id);
        if ($order === null) {
            Response::json(['status' => 'error', 'message' => 'Connection order not found'], 404);
            return;
        }

        $customer = $this->customers->findByCustomerId((string) $order['customer_id']);

        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Connection Summary</title><style>'
            . 'body{font-family:Arial,sans-serif;background:#f4f7fb;padding:20px;color:#111827;}'
            . '.sheet{max-width:980px;margin:0 auto;background:#fff;border:1px solid #dbe3ef;border-radius:12px;box-shadow:0 10px 28px rgba(13,35,67,.08);overflow:hidden;}'
            . '.head{padding:18px 22px;border-bottom:2px solid #d9e4f4;background:linear-gradient(180deg,#f8fbff,#f2f7ff);}'
            . '.head-top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;}'
            . '.brand{font-size:24px;font-weight:800;color:#123a72;letter-spacing:.4px;}'
            . '.addr{font-size:13px;color:#3f5472;margin-top:4px;}'
            . '.tag{background:#163f74;color:#fff;padding:6px 10px;border-radius:999px;font-size:11px;text-transform:uppercase;letter-spacing:.6px;}'
            . '.meta{margin-top:12px;font-size:12px;color:#5b6f8a;}'
            . '.content{padding:18px 22px 24px 22px;}'
            . 'h2{margin:0 0 12px 0;font-size:20px;color:#0e2b55;}'
            . '.grid{display:grid;grid-template-columns:repeat(3,minmax(160px,1fr));gap:8px;margin-bottom:14px;}'
            . '.cell{border:1px solid #d8e2ef;background:#f8fbff;padding:8px 10px;border-radius:8px;font-size:13px;}'
            . 'table{width:100%;border-collapse:collapse;}'
            . 'th,td{border:1px solid #d4deeb;padding:8px;text-align:left;font-size:13px;}'
            . 'th{background:#eef4fb;color:#1f3f68;}'
            . '.totals{display:grid;grid-template-columns:repeat(3,minmax(150px,1fr));gap:8px;margin-top:12px;}'
            . '.totals .cell{font-weight:700;}'
            . '.print-btn{display:inline-block;margin:0 0 12px 0;padding:8px 12px;border-radius:8px;background:#0f5cc8;color:#fff;text-decoration:none;}'
            . '@media print{body{background:#fff;padding:0}.sheet{border:none;box-shadow:none;border-radius:0}.print-btn{display:none}}'
            . '</style></head><body>';
        echo '<a class="print-btn" href="#" onclick="window.print();return false;">Print / Save as PDF</a>';
        echo '<div class="sheet"><div class="head"><div class="head-top"><div><div class="brand">BBN</div><div class="addr">Station Road, Badiakhali, Gaibandha</div></div><div class="tag">' . htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') . '</div></div><div class="meta">Generated: ' . htmlspecialchars(date('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8') . '</div></div><div class="content">';
        echo '<h2>Connection Summary</h2>';
        echo '<div class="grid">';
        echo '<div class="cell"><strong>Customer ID:</strong> ' . htmlspecialchars((string) $order['customer_id'], ENT_QUOTES, 'UTF-8') . '</div>';
        echo '<div class="cell"><strong>Customer Name:</strong> ' . htmlspecialchars((string) ($customer['full_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') . '</div>';
        echo '<div class="cell"><strong>Connection Date:</strong> ' . htmlspecialchars((string) ($order['connected_on'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
        echo '</div>';
        echo '<table><tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr>';

        foreach ($order['items'] as $item) {
            $productId = (string) ($item['product_id'] ?? '');
            $productName = $productId;
            if ($productId !== '') {
                $product = $this->products->findById($productId);
                if (is_array($product) && !empty($product['name'])) {
                    $productName = (string) $product['name'];
                }
            }

            echo '<tr>';
            echo '<td>' . htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string) $item['quantity'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars(number_format((float) $item['unit_price'], 2), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars(number_format((float) $item['line_total'], 2), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '</tr>';
        }

        echo '</table>';
        echo '<div class="totals">';
        echo '<div class="cell">Products Total: ' . number_format((float) ($order['products_total'] ?? 0), 2) . '</div>';
        echo '<div class="cell">Service Charge: ' . number_format((float) ($order['service_charge'] ?? 0), 2) . '</div>';
        echo '<div class="cell">Grand Total: ' . number_format((float) ($order['grand_total'] ?? 0), 2) . '</div>';
        echo '</div>';
        echo '</div></div></body></html>';
    }
}
