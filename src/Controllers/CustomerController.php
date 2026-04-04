<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\ConnectionRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\FinanceRepository;
use App\Repositories\PackageRepository;
use App\Repositories\ProductRepository;
use App\Repositories\TopologyRepository;
use App\Services\ConnectionCostService;

final class CustomerController
{
    public function __construct(
        private readonly CustomerRepository $customers = new CustomerRepository(),
        private readonly ConnectionRepository $connections = new ConnectionRepository(),
        private readonly ProductRepository $products = new ProductRepository(),
        private readonly ConnectionCostService $costService = new ConnectionCostService(),
        private readonly TopologyRepository $topology = new TopologyRepository(),
        private readonly FinanceRepository $finance = new FinanceRepository(),
    ) {
    }

    public function index(Request $request): void
    {
        $limit = max(1, min(200, (int) ($request->query['limit'] ?? 50)));

        Response::json([
            'status' => 'success',
            'data' => $this->customers->list($limit),
        ]);
    }

    public function checkUnique(Request $request): void
    {
        $customerId = trim((string) ($request->query['customer_id'] ?? ''));
        $phone = trim((string) ($request->query['phone'] ?? ''));

        Response::json([
            'status' => 'success',
            'data' => [
                'customer_id_exists' => $customerId !== '' ? $this->customers->isCustomerIdTaken($customerId) : false,
                'phone_exists' => $phone !== '' ? $this->customers->isPhoneTaken($phone) : false,
            ],
        ]);
    }

    public function store(Request $request): void
    {
        if (!is_array($request->body)) {
            Response::json(['status' => 'error', 'message' => 'Invalid JSON body'], 422);
            return;
        }

        $payload = $request->body;

        $required = ['customer_id', 'full_name'];
        foreach ($required as $field) {
            if (empty($payload[$field])) {
                Response::json([
                    'status' => 'error',
                    'message' => sprintf('%s is required', $field),
                ], 422);
                return;
            }
        }

        $payload['customer_id'] = trim((string) $payload['customer_id']);
        if ($this->customers->isCustomerIdTaken($payload['customer_id'])) {
            Response::json([
                'status' => 'error',
                'message' => 'customer_id already exists',
            ], 409);
            return;
        }

        $phone = trim((string) ($payload['phone'] ?? ''));
        if ($phone !== '' && $this->customers->isPhoneTaken($phone)) {
            Response::json([
                'status' => 'error',
                'message' => 'phone already exists',
            ], 409);
            return;
        }

        $payload['phone'] = $phone;

        $normalizedItems = $this->normalizeConnectionItems($payload['connection_items'] ?? []);
        if ($normalizedItems === null) {
            Response::json([
                'status' => 'error',
                'message' => 'connection_items must be an array of {product_id, quantity}',
            ], 422);
            return;
        }

        $payload['connection_items'] = $normalizedItems;

        $created = $this->customers->create($payload);

        Response::json([
            'status' => 'success',
            'message' => 'Customer created successfully',
            'data' => $created,
        ], 201);
    }

    public function update(Request $request): void
    {
        $customerId = trim((string) $request->input('customer_id', ''));
        if ($customerId === '') {
            Response::json(['status' => 'error', 'message' => 'customer_id is required'], 422);
            return;
        }

        $payload = is_array($request->body) ? $request->body : [];
        if (array_key_exists('phone', $payload)) {
            $phone = trim((string) $payload['phone']);
            if ($phone !== '' && $this->customers->isPhoneTaken($phone, $customerId)) {
                Response::json([
                    'status' => 'error',
                    'message' => 'phone already exists',
                ], 409);
                return;
            }

            $payload['phone'] = $phone;
        }

        if (array_key_exists('connection_items', $payload)) {
            $normalizedItems = $this->normalizeConnectionItems($payload['connection_items']);
            if ($normalizedItems === null) {
                Response::json([
                    'status' => 'error',
                    'message' => 'connection_items must be an array of {product_id, quantity}',
                ], 422);
                return;
            }

            $payload['connection_items'] = $normalizedItems;
        }

        $updated = $this->customers->updateByCustomerId($customerId, $payload);
        if (!$updated) {
            Response::json(['status' => 'error', 'message' => 'No changes made or customer not found'], 404);
            return;
        }

        Response::json(['status' => 'success', 'message' => 'Customer updated']);
    }

    public function storeWithConnection(Request $request): void
    {
        if (!is_array($request->body)) {
            Response::json(['status' => 'error', 'message' => 'Invalid JSON body'], 422);
            return;
        }

        $payload = $request->body;
        $required = ['customer_id', 'full_name'];
        foreach ($required as $field) {
            if (empty($payload[$field])) {
                Response::json([
                    'status' => 'error',
                    'message' => sprintf('%s is required', $field),
                ], 422);
                return;
            }
        }

        $payload['customer_id'] = trim((string) $payload['customer_id']);
        if ($this->customers->isCustomerIdTaken($payload['customer_id'])) {
            Response::json([
                'status' => 'error',
                'message' => 'customer_id already exists',
            ], 409);
            return;
        }

        $phone = trim((string) ($payload['phone'] ?? ''));
        if ($phone !== '' && $this->customers->isPhoneTaken($phone)) {
            Response::json([
                'status' => 'error',
                'message' => 'phone already exists',
            ], 409);
            return;
        }

        $payload['phone'] = $phone;

        $normalizedItems = $this->normalizeConnectionItems($payload['connection_items'] ?? []);
        if ($normalizedItems === null || $normalizedItems === []) {
            Response::json([
                'status' => 'error',
                'message' => 'At least one valid connection item is required',
            ], 422);
            return;
        }

        foreach ($normalizedItems as $item) {
            $product = $this->products->findById((string) $item['product_id']);
            if ($product === null) {
                Response::json([
                    'status' => 'error',
                    'message' => 'Invalid product_id ' . (string) $item['product_id'],
                ], 422);
                return;
            }

            $availableStock = (int) ($product['stock'] ?? 0);
            if ($availableStock < (int) $item['quantity']) {
                Response::json([
                    'status' => 'error',
                    'message' => 'Insufficient stock for product_id ' . (string) $item['product_id'],
                ], 422);
                return;
            }
        }

        $serviceCharge = (float) ($payload['service_charge'] ?? 0);
        $costPreview = $this->costService->preview($normalizedItems, $serviceCharge);

        $customerPayload = $payload;
        $customerPayload['connection_items'] = $normalizedItems;
        $customerPayload['status'] = 'pending_connection';

        $customerCreated = null;
        $decrementedItems = [];

        try {
            $customerCreated = $this->customers->create($customerPayload);

            foreach ($costPreview['items'] as $line) {
                $ok = $this->products->decrementStock((string) $line['product_id'], (int) $line['quantity']);
                if (!$ok) {
                    throw new \RuntimeException('Insufficient stock for product_id ' . (string) $line['product_id']);
                }

                $decrementedItems[] = $line;
            }

            $order = $this->connections->createOrder([
                'customer_id' => (string) $payload['customer_id'],
                'technician' => $payload['technician'] ?? null,
                'line_source_id' => $payload['line_source_id'] ?? null,
                'distribution_box_id' => $payload['distribution_box_id'] ?? null,
                'service_charge' => $costPreview['service_charge'],
                'products_total' => $costPreview['products_total'],
                'grand_total' => $costPreview['grand_total'],
                'items' => $costPreview['items'],
                'status' => $payload['connection_status'] ?? 'connected',
                'connected_on' => $payload['connected_on'] ?? date('Y-m-d'),
            ]);

            if (!empty($payload['distribution_box_id'])) {
                $this->topology->incrementUsedPort((string) $payload['distribution_box_id']);
            }

            $this->customers->setStatus((string) $payload['customer_id'], 'active');

            if ((float) $costPreview['grand_total'] > 0) {
                $this->finance->addIncome([
                    'date' => (string) ($order['connected_on'] ?? date('Y-m-d')),
                    'source' => 'new_connection',
                    'category' => 'connection_charge',
                    'amount' => (float) $costPreview['grand_total'],
                    'note' => 'Connection charge for ' . (string) $payload['customer_id'],
                ]);
            }

            Response::json([
                'status' => 'success',
                'message' => 'Customer and connection created successfully',
                'data' => [
                    'customer' => $customerCreated,
                    'connection' => $order,
                    'cost_preview' => $costPreview,
                ],
            ], 201);
        } catch (\Throwable $exception) {
            foreach ($decrementedItems as $line) {
                $this->products->incrementStock((string) $line['product_id'], (int) $line['quantity']);
            }

            if (is_array($customerCreated) && !empty($customerCreated['customer_id'])) {
                $this->customers->deleteByCustomerId((string) $customerCreated['customer_id']);
            }

            Response::json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function delete(Request $request): void
    {
        $customerId = trim((string) $request->input('customer_id', ''));
        if ($customerId === '') {
            Response::json(['status' => 'error', 'message' => 'customer_id is required'], 422);
            return;
        }

        $deleted = $this->customers->deleteByCustomerId($customerId);
        if (!$deleted) {
            Response::json(['status' => 'error', 'message' => 'Customer not found'], 404);
            return;
        }

        Response::json(['status' => 'success', 'message' => 'Customer deleted']);
    }

    public function printProfile(Request $request): void
    {
        $customerId = trim((string) ($request->query['customer_id'] ?? ''));
        $tag = trim((string) ($request->query['tag'] ?? 'Customer Copy'));
        if ($customerId === '') {
            Response::json(['status' => 'error', 'message' => 'customer_id query param is required'], 422);
            return;
        }

        $customer = $this->customers->findByCustomerId($customerId);
        if ($customer === null) {
            Response::json(['status' => 'error', 'message' => 'Customer not found'], 404);
            return;
        }

        $packageName = 'N/A';
        $packageId = (string) ($customer['package_id'] ?? '');
        if ($packageId !== '') {
            $packageRepo = new PackageRepository();
            $package = $packageRepo->findById($packageId);
            if (is_array($package) && !empty($package['name'])) {
                $packageName = (string) $package['name'];
            }
        }

        $connections = $this->connections->listByCustomerId($customerId, 200);
        $productsTotal = array_reduce($connections, fn (float $sum, array $row): float => $sum + (float) ($row['products_total'] ?? 0), 0.0);
        $serviceTotal = array_reduce($connections, fn (float $sum, array $row): float => $sum + (float) ($row['service_charge'] ?? 0), 0.0);
        $grandTotal = array_reduce($connections, fn (float $sum, array $row): float => $sum + (float) ($row['grand_total'] ?? 0), 0.0);

        $items = is_array($customer['connection_items'] ?? null) ? $customer['connection_items'] : [];
        $itemRows = '';
        if ($items === []) {
            $itemRows = '<tr><td colspan="2">No equipment requirements recorded.</td></tr>';
        } else {
            foreach ($items as $item) {
                $productId = (string) ($item['product_id'] ?? '');
                $qty = (int) ($item['quantity'] ?? 0);
                $productName = $productId;
                if ($productId !== '') {
                    $product = $this->products->findById($productId);
                    if (is_array($product) && !empty($product['name'])) {
                        $productName = (string) $product['name'];
                    }
                }

                $itemRows .= '<tr>'
                    . '<td>' . htmlspecialchars($productName) . '</td>'
                    . '<td>' . htmlspecialchars((string) $qty) . '</td>'
                    . '</tr>';
            }
        }

        $connectionRows = '';
        if ($connections === []) {
            $connectionRows = '<tr><td colspan="7">No connection records found for this customer.</td></tr>';
        } else {
            foreach ($connections as $row) {
                $connectionRows .= '<tr>'
                    . '<td>' . htmlspecialchars((string) ($row['connected_on'] ?? '')) . '</td>'
                    . '<td>' . htmlspecialchars((string) ($row['technician'] ?? '')) . '</td>'
                    . '<td>' . htmlspecialchars((string) ($row['status'] ?? '')) . '</td>'
                    . '<td>' . htmlspecialchars(number_format((float) ($row['products_total'] ?? 0), 2)) . '</td>'
                    . '<td>' . htmlspecialchars(number_format((float) ($row['service_charge'] ?? 0), 2)) . '</td>'
                    . '<td>' . htmlspecialchars(number_format((float) ($row['grand_total'] ?? 0), 2)) . '</td>'
                    . '<td><a href="/print/connection-summary?id=' . htmlspecialchars((string) ($row['_id'] ?? '')) . '&tag=Connection%20Copy" target="_blank">Print</a></td>'
                    . '</tr>';
            }
        }

        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Customer Profile - ' . htmlspecialchars($customerId) . '</title>';
        echo '<style>'
            . 'body{font-family:Segoe UI,Tahoma,sans-serif;color:#111827;margin:20px;background:#f4f7fb;}'
            . '.sheet{max-width:1080px;margin:0 auto;background:#fff;border:1px solid #dbe3ef;border-radius:12px;box-shadow:0 10px 28px rgba(13,35,67,.08);overflow:hidden;}'
            . '.head{padding:18px 22px;border-bottom:2px solid #d9e4f4;background:linear-gradient(180deg,#f8fbff,#f2f7ff);}'
            . '.head-top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;}'
            . '.brand{font-size:24px;font-weight:800;color:#123a72;letter-spacing:.4px;}'
            . '.addr{font-size:13px;color:#3f5472;margin-top:4px;}'
            . '.tag{background:#163f74;color:#fff;padding:6px 10px;border-radius:999px;font-size:11px;text-transform:uppercase;letter-spacing:.6px;}'
            . '.head-meta{margin-top:12px;font-size:12px;color:#5b6f8a;}'
            . '.content{padding:18px 22px 24px 22px;}'
            . 'h1,h2{margin:0 0 8px 0;}'
            . '.muted{color:#4b5563;font-size:13px;margin-bottom:14px;}'
            . '.section{margin-top:20px;}'
            . '.meta{display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:8px;}'
            . '.meta p{margin:0;padding:8px 10px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;}'
            . 'table{width:100%;border-collapse:collapse;margin-top:8px;}'
            . 'th,td{border:1px solid #d1d5db;padding:8px;text-align:left;font-size:13px;}'
            . 'th{background:#f3f4f6;}'
            . '.totals{display:grid;grid-template-columns:repeat(3,minmax(200px,1fr));gap:8px;margin-top:10px;}'
            . '.totals p{margin:0;padding:9px 10px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:8px;font-weight:600;}'
            . '@media print{.no-print{display:none;} body{margin:0;background:#fff;} .sheet{border:none;box-shadow:none;border-radius:0;}}'
            . '</style></head><body>';
        echo '<div class="no-print" style="margin:0 auto 12px auto;max-width:1080px;"><button onclick="window.print()">Print / Save as PDF</button></div>';
        echo '<div class="sheet"><div class="head"><div class="head-top"><div><div class="brand">BBN</div><div class="addr">Station Road, Badiakhali, Gaibandha</div></div><div class="tag">' . htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') . '</div></div><div class="head-meta">Generated on ' . htmlspecialchars(date('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8') . '</div></div><div class="content">';
        echo '<h1>Customer Profile</h1>';
        echo '<p class="muted">Detailed customer information, equipment requirement, and connection history.</p>';

        echo '<div class="section"><h2>Customer Information</h2><div class="meta">';
        echo '<p><strong>Customer ID:</strong> ' . htmlspecialchars((string) ($customer['customer_id'] ?? '')) . '</p>';
        echo '<p><strong>Full Name:</strong> ' . htmlspecialchars((string) ($customer['full_name'] ?? '')) . '</p>';
        echo '<p><strong>Phone:</strong> ' . htmlspecialchars((string) ($customer['phone'] ?? '')) . '</p>';
        echo '<p><strong>Email:</strong> ' . htmlspecialchars((string) ($customer['email'] ?? '')) . '</p>';
        echo '<p><strong>NID:</strong> ' . htmlspecialchars((string) ($customer['nid'] ?? '')) . '</p>';
        echo '<p><strong>Address:</strong> ' . htmlspecialchars((string) ($customer['address'] ?? '')) . '</p>';
        echo '<p><strong>Package:</strong> ' . htmlspecialchars($packageName) . '</p>';
        echo '<p><strong>Status:</strong> ' . htmlspecialchars((string) ($customer['status'] ?? '')) . '</p>';
        echo '<p><strong>Monthly Bill:</strong> ' . htmlspecialchars(number_format((float) ($customer['monthly_bill_amount'] ?? 0), 2)) . '</p>';
        echo '<p><strong>Due Amount:</strong> ' . htmlspecialchars(number_format((float) ($customer['due_amount'] ?? 0), 2)) . '</p>';
        echo '</div></div>';

        echo '<div class="section"><h2>Required Equipment</h2>';
        echo '<table><thead><tr><th>Product</th><th>Quantity</th></tr></thead><tbody>' . $itemRows . '</tbody></table>';
        echo '</div>';

        echo '<div class="section"><h2>Connection History</h2>';
        echo '<table><thead><tr><th>Date</th><th>Technician</th><th>Status</th><th>Products</th><th>Service</th><th>Grand</th><th>Print</th></tr></thead><tbody>' . $connectionRows . '</tbody></table>';
        echo '<div class="totals">'
            . '<p>Total Products: ' . htmlspecialchars(number_format($productsTotal, 2)) . '</p>'
            . '<p>Total Service: ' . htmlspecialchars(number_format($serviceTotal, 2)) . '</p>'
            . '<p>Total Grand: ' . htmlspecialchars(number_format($grandTotal, 2)) . '</p>'
            . '</div>';
        echo '</div>';

        echo '</div></div></body></html>';
    }

    private function normalizeConnectionItems(mixed $items): ?array
    {
        if ($items === null) {
            return [];
        }

        if (!is_array($items)) {
            return null;
        }

        $merged = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                return null;
            }

            $productId = trim((string) ($item['product_id'] ?? ''));
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($productId === '' || $quantity <= 0) {
                return null;
            }

            if (!isset($merged[$productId])) {
                $merged[$productId] = ['product_id' => $productId, 'quantity' => 0];
            }

            $merged[$productId]['quantity'] += $quantity;
        }

        return array_values($merged);
    }
}
