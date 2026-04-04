<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\BillingRepository;
use App\Repositories\ConnectionRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\FinanceRepository;
use App\Repositories\ProductRepository;

final class ReportController
{
    public function __construct(
        private readonly FinanceRepository $finance = new FinanceRepository(),
        private readonly BillingRepository $billing = new BillingRepository(),
        private readonly CustomerRepository $customers = new CustomerRepository(),
        private readonly ProductRepository $products = new ProductRepository(),
        private readonly ConnectionRepository $connections = new ConnectionRepository(),
    )
    {
    }

    public function overview(Request $request): void
    {
        $month = $this->month($request);
        $summary = $this->finance->monthlySummary($month);

        $bills = $this->billing->listBills($month, 5000);
        $payments = $this->billing->listPayments('', 5000, $month);
        $customerRows = $this->customers->list(5000);
        $productRows = $this->products->list(5000);
        $connectionRows = $this->connections->list(5000);

        $billTotal = array_reduce($bills, fn (float $carry, array $item): float => $carry + (float) ($item['total_bill'] ?? 0), 0.0);
        $paidTotal = array_reduce($bills, fn (float $carry, array $item): float => $carry + (float) ($item['paid_amount'] ?? 0), 0.0);
        $dueTotal = array_reduce($bills, fn (float $carry, array $item): float => $carry + (float) ($item['due_amount'] ?? 0), 0.0);
        $paymentTotal = array_reduce($payments, fn (float $carry, array $item): float => $carry + (float) ($item['amount'] ?? 0), 0.0);
        $inventorySellValue = array_reduce($productRows, fn (float $carry, array $item): float => $carry + ((float) ($item['price'] ?? 0) * (int) ($item['stock'] ?? 0)), 0.0);
        $inventoryCostValue = array_reduce($productRows, fn (float $carry, array $item): float => $carry + ((float) ($item['cost_price'] ?? 0) * (int) ($item['stock'] ?? 0)), 0.0);

        Response::json([
            'status' => 'success',
            'data' => [
                'month' => $month,
                'income_total' => (float) ($summary['income_total'] ?? 0),
                'expense_total' => (float) ($summary['expense_total'] ?? 0),
                'net_total' => (float) ($summary['net'] ?? 0),
                'bill_total' => round($billTotal, 2),
                'bill_paid_total' => round($paidTotal, 2),
                'bill_due_total' => round($dueTotal, 2),
                'payment_total' => round($paymentTotal, 2),
                'customers_total' => count($customerRows),
                'connections_total' => count($connectionRows),
                'products_total' => count($productRows),
                'inventory_sell_value' => round($inventorySellValue, 2),
                'inventory_cost_value' => round($inventoryCostValue, 2),
            ],
        ]);
    }

    public function incomeExpensePrint(Request $request): void
    {
        $month = $this->month($request);
        $tag = trim((string) ($request->query['tag'] ?? 'Accounts Copy'));
        $summary = $this->finance->monthlySummary($month);
        $income = $this->finance->listIncome($month, 5000);
        $expense = $this->finance->listExpense($month, 5000);

        header('Content-Type: text/html; charset=utf-8');
        echo '<html><head><title>Income Expense Statement</title><style>body{font-family:Arial,sans-serif;padding:20px;color:#111;background:#f4f7fb}.sheet{max-width:1050px;margin:0 auto;background:#fff;border:1px solid #dbe3ef;border-radius:12px;box-shadow:0 10px 28px rgba(13,35,67,.08);overflow:hidden}.head{padding:18px 22px;border-bottom:2px solid #d9e4f4;background:linear-gradient(180deg,#f8fbff,#f2f7ff)}.head-top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}.brand{font-size:24px;font-weight:800;color:#123a72;letter-spacing:.4px}.addr{font-size:13px;color:#3f5472;margin-top:4px}.tag{background:#163f74;color:#fff;padding:6px 10px;border-radius:999px;font-size:11px;text-transform:uppercase;letter-spacing:.6px}.head-meta{margin-top:12px;font-size:12px;color:#5b6f8a}.content{padding:18px 22px 24px 22px}h2{margin:0 0 8px 0}h3{margin-top:20px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #cfd8e3;padding:8px;font-size:13px;text-align:left}th{background:#eef3fa}.top{display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:10px;margin:12px 0 18px 0}.box{border:1px solid #d6e0ec;border-radius:8px;padding:10px;background:#f8fbff}.print-btn{margin:0 auto 12px auto;display:block;max-width:1050px;padding:8px 12px;border-radius:6px;background:#0f5cc8;color:#fff;text-decoration:none;width:max-content}@media print{.print-btn{display:none}body{padding:0;background:#fff}.sheet{border:none;box-shadow:none;border-radius:0}}</style></head><body>';
        echo '<a class="print-btn" href="#" onclick="window.print();return false;">Print</a>';
        echo '<div class="sheet"><div class="head"><div class="head-top"><div><div class="brand">BBN</div><div class="addr">Station Road, Badiakhali, Gaibandha</div></div><div class="tag">' . $this->e($tag) . '</div></div><div class="head-meta">Generated: ' . $this->e(date('Y-m-d H:i:s')) . '</div></div><div class="content">';
        echo '<h2>Monthly Income and Expense Statement</h2>';
        echo '<p><strong>Month:</strong> ' . $this->e($month) . '</p>';
        echo '<div class="top">';
        echo '<div class="box"><strong>Total Income</strong><br>' . number_format((float) ($summary['income_total'] ?? 0), 2) . '</div>';
        echo '<div class="box"><strong>Total Expense</strong><br>' . number_format((float) ($summary['expense_total'] ?? 0), 2) . '</div>';
        echo '<div class="box"><strong>Net</strong><br>' . number_format((float) ($summary['net'] ?? 0), 2) . '</div>';
        echo '<div class="box"><strong>Entries</strong><br>' . ((int) ($summary['income_count'] ?? 0) + (int) ($summary['expense_count'] ?? 0)) . '</div>';
        echo '</div>';

        echo '<h3>Income</h3>';
        echo '<table border="1" cellpadding="6" cellspacing="0"><tr><th>Date</th><th>Source</th><th>Category</th><th>Amount</th></tr>';
        foreach ($income as $row) {
            echo '<tr><td>' . $this->e((string) ($row['date'] ?? '')) . '</td><td>' . $this->e((string) ($row['source'] ?? '')) . '</td><td>' . $this->e((string) ($row['category'] ?? '')) . '</td><td>' . number_format((float) ($row['amount'] ?? 0), 2) . '</td></tr>';
        }
        echo '</table>';

        echo '<h3>Expense</h3>';
        echo '<table border="1" cellpadding="6" cellspacing="0"><tr><th>Date</th><th>Category</th><th>Amount</th></tr>';
        foreach ($expense as $row) {
            echo '<tr><td>' . $this->e((string) ($row['date'] ?? '')) . '</td><td>' . $this->e((string) ($row['category'] ?? '')) . '</td><td>' . number_format((float) ($row['amount'] ?? 0), 2) . '</td></tr>';
        }
        echo '</table>';
        echo '</div></div></body></html>';
    }

    public function incomeExpenseCsv(Request $request): void
    {
        $month = $this->month($request);
        $income = $this->finance->listIncome($month, 10000);
        $expense = $this->finance->listExpense($month, 10000);

        $rows = [];
        foreach ($income as $row) {
            $rows[] = [
                'income',
                (string) ($row['date'] ?? ''),
                (string) ($row['source'] ?? ''),
                (string) ($row['category'] ?? ''),
                (float) ($row['amount'] ?? 0),
                (string) ($row['note'] ?? ''),
            ];
        }
        foreach ($expense as $row) {
            $rows[] = [
                'expense',
                (string) ($row['date'] ?? ''),
                '',
                (string) ($row['category'] ?? ''),
                (float) ($row['amount'] ?? 0),
                (string) ($row['note'] ?? ''),
            ];
        }

        usort($rows, fn (array $a, array $b): int => strcmp((string) $b[1], (string) $a[1]));
        $this->outputCsv('income_expense_' . $month . '.csv', ['type', 'date', 'source', 'category', 'amount', 'note'], $rows);
    }

    public function transactionsCsv(Request $request): void
    {
        $month = $this->month($request);
        $income = $this->finance->listIncome($month, 10000);
        $expense = $this->finance->listExpense($month, 10000);
        $payments = $this->billing->listPayments('', 10000, $month);
        $customerMap = $this->customerMap();

        $rows = [];
        foreach ($income as $row) {
            $rows[] = [
                'income',
                (string) ($row['date'] ?? ''),
                (string) ($row['category'] ?? ''),
                (string) ($row['source'] ?? ''),
                '',
                (float) ($row['amount'] ?? 0),
                (string) ($row['note'] ?? ''),
            ];
        }
        foreach ($expense as $row) {
            $rows[] = [
                'expense',
                (string) ($row['date'] ?? ''),
                (string) ($row['category'] ?? ''),
                '',
                '',
                (float) ($row['amount'] ?? 0),
                (string) ($row['note'] ?? ''),
            ];
        }
        foreach ($payments as $row) {
            $customerId = (string) ($row['customer_id'] ?? '');
            $rows[] = [
                'payment',
                substr((string) ($row['created_at'] ?? ''), 0, 10),
                (string) ($row['method'] ?? ''),
                (string) ($customerMap[$customerId] ?? ''),
                $customerId,
                (float) ($row['amount'] ?? 0),
                (string) ($row['reference'] ?? ''),
            ];
        }

        usort($rows, fn (array $a, array $b): int => strcmp((string) $b[1], (string) $a[1]));
        $this->outputCsv('transactions_' . $month . '.csv', ['type', 'date', 'category_or_method', 'source_or_customer', 'customer_id', 'amount', 'note_or_reference'], $rows);
    }

    public function billsCsv(Request $request): void
    {
        $month = $this->month($request);
        $rows = $this->billing->listBills($month, 10000);
        $customerMap = $this->customerMap();

        $csv = [];
        foreach ($rows as $row) {
            $customerId = (string) ($row['customer_id'] ?? '');
            $csv[] = [
                $customerId,
                (string) ($customerMap[$customerId] ?? ''),
                (string) ($row['billing_month'] ?? ''),
                (float) ($row['monthly_bill_amount'] ?? 0),
                (float) ($row['previous_due'] ?? 0),
                (float) ($row['total_bill'] ?? 0),
                (float) ($row['paid_amount'] ?? 0),
                (float) ($row['due_amount'] ?? 0),
                (string) ($row['status'] ?? ''),
            ];
        }

        $this->outputCsv('bills_' . $month . '.csv', ['customer_id', 'customer_name', 'billing_month', 'monthly_bill_amount', 'previous_due', 'total_bill', 'paid_amount', 'due_amount', 'status'], $csv);
    }

    public function paymentsCsv(Request $request): void
    {
        $month = $this->month($request);
        $rows = $this->billing->listPayments('', 10000, $month);
        $customerMap = $this->customerMap();

        $csv = [];
        foreach ($rows as $row) {
            $customerId = (string) ($row['customer_id'] ?? '');
            $csv[] = [
                substr((string) ($row['created_at'] ?? ''), 0, 10),
                $customerId,
                (string) ($customerMap[$customerId] ?? ''),
                (string) ($row['bill_month'] ?? ''),
                (float) ($row['amount'] ?? 0),
                (string) ($row['method'] ?? ''),
                (string) ($row['collector'] ?? ''),
                (string) ($row['reference'] ?? ''),
            ];
        }

        $this->outputCsv('payments_' . $month . '.csv', ['date', 'customer_id', 'customer_name', 'bill_month', 'amount', 'method', 'collector', 'reference'], $csv);
    }

    public function customersCsv(Request $request): void
    {
        $rows = $this->customers->list(10000);

        $csv = [];
        foreach ($rows as $row) {
            $items = [];
            if (is_array($row['connection_items'] ?? null)) {
                foreach ($row['connection_items'] as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $name = (string) ($item['product_name'] ?? $item['product_id'] ?? 'item');
                    $qty = (int) ($item['quantity'] ?? 0);
                    $items[] = $name . ' x ' . $qty;
                }
            }

            $csv[] = [
                (string) ($row['customer_id'] ?? ''),
                (string) ($row['full_name'] ?? ''),
                (string) ($row['phone'] ?? ''),
                (string) ($row['email'] ?? ''),
                (string) ($row['address'] ?? ''),
                (string) ($row['status'] ?? ''),
                (float) ($row['monthly_bill_amount'] ?? 0),
                (float) ($row['due_amount'] ?? 0),
                implode('; ', $items),
            ];
        }

        $this->outputCsv('customers.csv', ['customer_id', 'full_name', 'phone', 'email', 'address', 'status', 'monthly_bill_amount', 'due_amount', 'connection_items'], $csv);
    }

    public function inventoryCsv(Request $request): void
    {
        $rows = $this->products->list(10000);
        $csv = [];

        foreach ($rows as $row) {
            $stock = (int) ($row['stock'] ?? 0);
            $price = (float) ($row['price'] ?? 0);
            $costPrice = (float) ($row['cost_price'] ?? 0);
            $csv[] = [
                (string) ($row['sku'] ?? ''),
                (string) ($row['name'] ?? ''),
                (string) ($row['category'] ?? ''),
                $stock,
                $price,
                $costPrice,
                round($stock * $price, 2),
                round($stock * $costPrice, 2),
                (int) ($row['reorder_level'] ?? 0),
            ];
        }

        $this->outputCsv('inventory_snapshot.csv', ['sku', 'name', 'category', 'stock', 'sell_price', 'cost_price', 'stock_sell_value', 'stock_cost_value', 'reorder_level'], $csv);
    }

    public function connectionsCsv(Request $request): void
    {
        $rows = $this->connections->list(10000);
        $customerMap = $this->customerMap();
        $csv = [];

        foreach ($rows as $row) {
            $customerId = (string) ($row['customer_id'] ?? '');
            $csv[] = [
                (string) ($row['_id'] ?? ''),
                $customerId,
                (string) ($customerMap[$customerId] ?? ''),
                (string) ($row['status'] ?? ''),
                (float) ($row['service_charge'] ?? 0),
                (string) ($row['technician'] ?? ''),
                (string) ($row['connected_on'] ?? ''),
                substr((string) ($row['created_at'] ?? ''), 0, 10),
            ];
        }

        $this->outputCsv('connections.csv', ['connection_id', 'customer_id', 'customer_name', 'status', 'service_charge', 'technician', 'connected_on', 'created_date'], $csv);
    }

    public function transactionsPrint(Request $request): void
    {
        $month = $this->month($request);
        $tag = trim((string) ($request->query['tag'] ?? 'Transaction History'));
        $income = $this->finance->listIncome($month, 3000);
        $expense = $this->finance->listExpense($month, 3000);
        $payments = $this->billing->listPayments('', 3000, $month);
        $customerMap = $this->customerMap();

        $rows = [];
        foreach ($income as $row) {
            $rows[] = ['Income', (string) ($row['date'] ?? ''), (string) ($row['category'] ?? ''), (string) ($row['source'] ?? ''), (float) ($row['amount'] ?? 0), (string) ($row['note'] ?? '')];
        }
        foreach ($expense as $row) {
            $rows[] = ['Expense', (string) ($row['date'] ?? ''), (string) ($row['category'] ?? ''), '-', (float) ($row['amount'] ?? 0), (string) ($row['note'] ?? '')];
        }
        foreach ($payments as $row) {
            $cid = (string) ($row['customer_id'] ?? '');
            $rows[] = ['Payment', substr((string) ($row['created_at'] ?? ''), 0, 10), (string) ($row['method'] ?? ''), (string) ($customerMap[$cid] ?? $cid), (float) ($row['amount'] ?? 0), (string) ($row['reference'] ?? '')];
        }

        usort($rows, fn (array $a, array $b): int => strcmp((string) $b[1], (string) $a[1]));

        header('Content-Type: text/html; charset=utf-8');
        echo '<html><head><title>Transaction Ledger</title><style>body{font-family:Arial,sans-serif;padding:20px;color:#111;background:#f4f7fb}.sheet{max-width:1050px;margin:0 auto;background:#fff;border:1px solid #dbe3ef;border-radius:12px;box-shadow:0 10px 28px rgba(13,35,67,.08);overflow:hidden}.head{padding:18px 22px;border-bottom:2px solid #d9e4f4;background:linear-gradient(180deg,#f8fbff,#f2f7ff)}.head-top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}.brand{font-size:24px;font-weight:800;color:#123a72;letter-spacing:.4px}.addr{font-size:13px;color:#3f5472;margin-top:4px}.tag{background:#163f74;color:#fff;padding:6px 10px;border-radius:999px;font-size:11px;text-transform:uppercase;letter-spacing:.6px}.head-meta{margin-top:12px;font-size:12px;color:#5b6f8a}.content{padding:18px 22px 24px 22px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #d6e0ec;padding:8px;font-size:13px;text-align:left}th{background:#eef3fa}.print-btn{margin:0 auto 12px auto;display:block;max-width:1050px;padding:8px 12px;border-radius:6px;background:#0f5cc8;color:#fff;text-decoration:none;width:max-content}@media print{.print-btn{display:none}body{padding:0;background:#fff}.sheet{border:none;box-shadow:none;border-radius:0}}</style></head><body>';
        echo '<a class="print-btn" href="#" onclick="window.print();return false;">Print</a>';
        echo '<div class="sheet"><div class="head"><div class="head-top"><div><div class="brand">BBN</div><div class="addr">Station Road, Badiakhali, Gaibandha</div></div><div class="tag">' . $this->e($tag) . '</div></div><div class="head-meta">Generated: ' . $this->e(date('Y-m-d H:i:s')) . '</div></div><div class="content">';
        echo '<h2>Transaction Ledger</h2>';
        echo '<p><strong>Month:</strong> ' . $this->e($month) . '</p>';
        echo '<table><tr><th>Type</th><th>Date</th><th>Category/Method</th><th>Source/Customer</th><th>Amount</th><th>Note/Reference</th></tr>';
        foreach ($rows as $row) {
            echo '<tr><td>' . $this->e((string) $row[0]) . '</td><td>' . $this->e((string) $row[1]) . '</td><td>' . $this->e((string) $row[2]) . '</td><td>' . $this->e((string) $row[3]) . '</td><td>' . number_format((float) $row[4], 2) . '</td><td>' . $this->e((string) $row[5]) . '</td></tr>';
        }
        echo '</table></div></div></body></html>';
    }

    public function customersPrint(Request $request): void
    {
        $tag = trim((string) ($request->query['tag'] ?? 'Customer Copy'));
        $rows = $this->customers->list(10000);

        header('Content-Type: text/html; charset=utf-8');
        echo '<html><head><title>Customer List</title><style>body{font-family:Arial,sans-serif;padding:20px;color:#111;background:#f4f7fb}.sheet{max-width:1050px;margin:0 auto;background:#fff;border:1px solid #dbe3ef;border-radius:12px;box-shadow:0 10px 28px rgba(13,35,67,.08);overflow:hidden}.head{padding:18px 22px;border-bottom:2px solid #d9e4f4;background:linear-gradient(180deg,#f8fbff,#f2f7ff)}.head-top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}.brand{font-size:24px;font-weight:800;color:#123a72;letter-spacing:.4px}.addr{font-size:13px;color:#3f5472;margin-top:4px}.tag{background:#163f74;color:#fff;padding:6px 10px;border-radius:999px;font-size:11px;text-transform:uppercase;letter-spacing:.6px}.head-meta{margin-top:12px;font-size:12px;color:#5b6f8a}.content{padding:18px 22px 24px 22px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #d6e0ec;padding:8px;font-size:13px;text-align:left}th{background:#eef3fa}.print-btn{margin:0 auto 12px auto;display:block;max-width:1050px;padding:8px 12px;border-radius:6px;background:#0f5cc8;color:#fff;text-decoration:none;width:max-content}@media print{.print-btn{display:none}body{padding:0;background:#fff}.sheet{border:none;box-shadow:none;border-radius:0}}</style></head><body>';
        echo '<a class="print-btn" href="#" onclick="window.print();return false;">Print</a>';
        echo '<div class="sheet"><div class="head"><div class="head-top"><div><div class="brand">BBN</div><div class="addr">Station Road, Badiakhali, Gaibandha</div></div><div class="tag">' . $this->e($tag) . '</div></div><div class="head-meta">Generated: ' . $this->e(date('Y-m-d H:i:s')) . '</div></div><div class="content">';
        echo '<h2>Customer List</h2>';
        echo '<table><tr><th>Customer ID</th><th>Name</th><th>Phone</th><th>Email</th><th>Status</th><th>Monthly Bill</th><th>Due</th></tr>';
        foreach ($rows as $row) {
            echo '<tr><td>' . $this->e((string) ($row['customer_id'] ?? '')) . '</td><td>' . $this->e((string) ($row['full_name'] ?? '')) . '</td><td>' . $this->e((string) ($row['phone'] ?? '')) . '</td><td>' . $this->e((string) ($row['email'] ?? '')) . '</td><td>' . $this->e((string) ($row['status'] ?? '')) . '</td><td>' . number_format((float) ($row['monthly_bill_amount'] ?? 0), 2) . '</td><td>' . number_format((float) ($row['due_amount'] ?? 0), 2) . '</td></tr>';
        }
        echo '</table></div></div></body></html>';
    }

    private function customerMap(): array
    {
        $rows = $this->customers->list(10000);
        $map = [];
        foreach ($rows as $row) {
            $map[(string) ($row['customer_id'] ?? '')] = (string) ($row['full_name'] ?? '');
        }
        return $map;
    }

    private function outputCsv(string $filename, array $header, array $rows): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'wb');
        if ($output === false) {
            return;
        }

        fputcsv($output, $header);
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
    }

    private function month(Request $request): string
    {
        $month = (string) ($request->query['month'] ?? date('Y-m'));
        return preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m');
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
