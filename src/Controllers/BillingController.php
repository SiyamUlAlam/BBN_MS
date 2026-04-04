<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\BillingRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\FinanceRepository;
use App\Services\ConnectionCostService;

final class BillingController
{
    public function __construct(
        private readonly ConnectionCostService $costService = new ConnectionCostService(),
        private readonly BillingRepository $billing = new BillingRepository(),
        private readonly CustomerRepository $customers = new CustomerRepository(),
        private readonly FinanceRepository $finance = new FinanceRepository(),
    ) {
    }

    public function previewConnectionCost(Request $request): void
    {
        if (!is_array($request->body)) {
            Response::json(['status' => 'error', 'message' => 'Invalid JSON body'], 422);
            return;
        }

        $items = is_array($request->body['items'] ?? null) ? $request->body['items'] : [];
        $serviceCharge = (float) ($request->body['service_charge'] ?? 0);

        $preview = $this->costService->preview($items, $serviceCharge);

        Response::json([
            'status' => 'success',
            'data' => $preview,
        ]);
    }

    public function generateMonthlyBills(Request $request): void
    {
        $month = is_array($request->body) ? (string) ($request->body['month'] ?? date('Y-m')) : date('Y-m');
        $customers = $this->customers->list(10000);

        $count = 0;
        foreach ($customers as $customer) {
            if (($customer['status'] ?? '') !== 'active') {
                continue;
            }

            $monthly = (float) ($customer['monthly_bill_amount'] ?? 0);
            $previousDue = (float) ($customer['due_amount'] ?? 0);
            $totalBill = $monthly + $previousDue;

            $this->billing->createOrUpdateMonthlyBill([
                'customer_id' => $customer['customer_id'],
                'billing_month' => $month,
                'package_id' => $customer['package_id'] ?? null,
                'monthly_bill_amount' => $monthly,
                'previous_due' => $previousDue,
                'total_bill' => $totalBill,
                'paid_amount' => 0,
                'due_amount' => $totalBill,
                'status' => $totalBill > 0 ? 'unpaid' : 'paid',
            ]);
            $count++;
        }

        Response::json([
            'status' => 'success',
            'message' => 'Monthly bills generated',
            'data' => ['month' => $month, 'generated_count' => $count],
        ]);
    }

    public function listBills(Request $request): void
    {
        $month = (string) ($request->query['month'] ?? '');
        Response::json([
            'status' => 'success',
            'data' => $this->billing->listBills($month),
        ]);
    }

    public function postPayment(Request $request): void
    {
        if (!is_array($request->body) || empty($request->body['customer_id']) || !isset($request->body['amount'])) {
            Response::json(['status' => 'error', 'message' => 'customer_id and amount are required'], 422);
            return;
        }

        $customerId = (string) $request->body['customer_id'];
        $amount = (float) $request->body['amount'];
        $month = (string) ($request->body['bill_month'] ?? date('Y-m'));
        $customer = $this->customers->findByCustomerId($customerId);

        if ($customer === null) {
            Response::json(['status' => 'error', 'message' => 'Customer not found'], 404);
            return;
        }

        $existingBill = $this->billing->findBillByCustomerMonth($customerId, $month);
        if ($existingBill === null) {
            $currentDue = (float) ($customer['due_amount'] ?? 0);
            $monthlyAmount = (float) ($customer['monthly_bill_amount'] ?? 0);
            $totalBill = $currentDue + $monthlyAmount;

            $this->billing->createOrUpdateMonthlyBill([
                'customer_id' => $customerId,
                'billing_month' => $month,
                'package_id' => $customer['package_id'] ?? null,
                'monthly_bill_amount' => $monthlyAmount,
                'previous_due' => $currentDue,
                'total_bill' => $totalBill,
                'paid_amount' => 0,
                'due_amount' => $totalBill,
                'status' => $totalBill > 0 ? 'unpaid' : 'paid',
            ]);

            $existingBill = $this->billing->findBillByCustomerMonth($customerId, $month);
        }

        $billTotal = (float) (($existingBill['total_bill'] ?? 0));
        $billPaid = (float) (($existingBill['paid_amount'] ?? 0));
        $newBillPaid = $billPaid + $amount;
        $newDue = max(0, $billTotal - $newBillPaid);

        $payment = $this->billing->addPayment([
            'customer_id' => $customerId,
            'bill_month' => $month,
            'amount' => $amount,
            'method' => $request->body['method'] ?? 'cash',
            'collector' => $request->body['collector'] ?? null,
            'reference' => $request->body['reference'] ?? null,
        ]);

        $this->customers->updateDue($customerId, $newDue);
        $this->billing->updateBillPayment($customerId, $month, $newBillPaid, $newDue);

        $this->finance->addIncome([
            'date' => date('Y-m-d'),
            'source' => 'customer_payment',
            'category' => 'subscription',
            'amount' => $amount,
            'note' => 'Payment from ' . $customerId,
        ]);

        Response::json([
            'status' => 'success',
            'message' => 'Payment posted successfully',
            'data' => [
                'payment' => $payment,
                'new_due_amount' => $newDue,
            ],
        ], 201);
    }

    public function listPayments(Request $request): void
    {
        $customerId = (string) ($request->query['customer_id'] ?? '');
        $month = (string) ($request->query['month'] ?? '');
        Response::json([
            'status' => 'success',
            'data' => $this->billing->listPayments($customerId, 200, $month),
        ]);
    }
}
