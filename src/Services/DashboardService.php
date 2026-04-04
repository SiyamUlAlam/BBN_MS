<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\BillingRepository;
use App\Repositories\ConnectionRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\FinanceRepository;
use App\Repositories\ProductRepository;

final class DashboardService
{
    public function __construct(
        private readonly CustomerRepository $customers = new CustomerRepository(),
        private readonly ConnectionRepository $connections = new ConnectionRepository(),
        private readonly BillingRepository $billing = new BillingRepository(),
        private readonly FinanceRepository $finance = new FinanceRepository(),
        private readonly ProductRepository $products = new ProductRepository(),
    ) {
    }

    public function summary(string $month): array
    {
        $customerRows = $this->customers->list(10000);
        $activeCustomers = count(array_filter($customerRows, fn (array $c): bool => ($c['status'] ?? '') === 'active'));
        $totalDue = array_reduce($customerRows, fn (float $carry, array $c): float => $carry + (float) ($c['due_amount'] ?? 0), 0.0);

        $connections = $this->connections->list(1000);
        $payments = $this->billing->listPayments('', 10000, $month);
        $incomeExpense = $this->finance->monthlySummary($month);
        $products = $this->products->list(10000);
        $lowStock = count(array_filter($products, fn (array $p): bool => (int) ($p['stock'] ?? 0) <= (int) ($p['reorder_level'] ?? 0)));
        $paidTotal = array_reduce($payments, fn (float $carry, array $p): float => $carry + (float) ($p['amount'] ?? 0), 0.0);
        $allPayments = $this->billing->listPayments('', 50000, '');

        $monthConnections = array_filter(
            $connections,
            fn (array $row): bool => str_starts_with((string) ($row['connected_on'] ?? ''), $month)
        );

        $productSoldIncome = array_reduce(
            $monthConnections,
            fn (float $carry, array $row): float => $carry + (float) ($row['products_total'] ?? 0),
            0.0
        );

        $connectionChargeIncome = array_reduce(
            $monthConnections,
            fn (float $carry, array $row): float => $carry + (float) ($row['service_charge'] ?? 0),
            0.0
        );

        $connectionTotalIncome = $productSoldIncome + $connectionChargeIncome;

        $productSoldCost = 0.0;
        foreach ($monthConnections as $connection) {
            $items = is_array($connection['items'] ?? null) ? $connection['items'] : [];
            foreach ($items as $item) {
                $productSoldCost += (float) ($item['unit_cost'] ?? 0) * (int) ($item['quantity'] ?? 0);
            }
        }

        $inventoryCostValue = array_reduce(
            $products,
            fn (float $carry, array $p): float => $carry + ((float) ($p['cost_price'] ?? 0) * (int) ($p['stock'] ?? 0)),
            0.0
        );

        $inventorySellValue = array_reduce(
            $products,
            fn (float $carry, array $p): float => $carry + ((float) ($p['price'] ?? 0) * (int) ($p['stock'] ?? 0)),
            0.0
        );

        $inventoryStockQuantity = array_reduce(
            $products,
            fn (int $carry, array $p): int => $carry + (int) ($p['stock'] ?? 0),
            0
        );

        $today = date('Y-m-d');
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $todayCollection = 0.0;
        $weekCollection = 0.0;

        foreach ($allPayments as $payment) {
            $createdAt = (string) ($payment['created_at'] ?? '');
            if ($createdAt === '') {
                continue;
            }

            $day = substr($createdAt, 0, 10);
            $amount = (float) ($payment['amount'] ?? 0);
            if ($day === $today) {
                $todayCollection += $amount;
            }

            if ($day >= $weekStart && $day <= $today) {
                $weekCollection += $amount;
            }
        }

        $companyExpense = (float) ($incomeExpense['expense_total'] ?? 0);
        $totalIncome = $connectionTotalIncome + $paidTotal;
        $net = $totalIncome - $companyExpense;

        return [
            'month' => $month,
            'customers_total' => count($customerRows),
            'customers_active' => $activeCustomers,
            'total_due' => round($totalDue, 2),
            'connections_total' => count($monthConnections),
            'product_sold_income' => round($productSoldIncome, 2),
            'connection_charge_income' => round($connectionChargeIncome, 2),
            'connection_total_income' => round($connectionTotalIncome, 2),
            'product_sold_cost' => round($productSoldCost, 2),
            'customer_bill_payment_total' => round($paidTotal, 2),
            'today_collection_amount' => round($todayCollection, 2),
            'week_collection_amount' => round($weekCollection, 2),
            'inventory_stock_quantity' => $inventoryStockQuantity,
            'inventory_sell_value' => round($inventorySellValue, 2),
            'inventory_cost_value' => round($inventoryCostValue, 2),
            'company_expense_total' => round($companyExpense, 2),
            'total_income' => round($totalIncome, 2),
            'net_income' => round($net, 2),
            'low_stock_products' => $lowStock,
        ];
    }
}
