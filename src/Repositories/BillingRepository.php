<?php

declare(strict_types=1);

namespace App\Repositories;

use MongoDB\BSON\UTCDateTime;

final class BillingRepository extends BaseRepository
{
    public function createOrUpdateMonthlyBill(array $payload): void
    {
        $now = new UTCDateTime();

        $this->collection('bills')->updateOne(
            [
                'customer_id' => $payload['customer_id'],
                'billing_month' => $payload['billing_month'],
            ],
            [
                '$set' => [
                    'package_id' => $payload['package_id'] ?? null,
                    'monthly_bill_amount' => (float) ($payload['monthly_bill_amount'] ?? 0),
                    'previous_due' => (float) ($payload['previous_due'] ?? 0),
                    'total_bill' => (float) ($payload['total_bill'] ?? 0),
                    'paid_amount' => (float) ($payload['paid_amount'] ?? 0),
                    'due_amount' => (float) ($payload['due_amount'] ?? 0),
                    'status' => $payload['status'] ?? 'unpaid',
                    'updated_at' => $now,
                ],
                '$setOnInsert' => [
                    'created_at' => $now,
                ],
            ],
            ['upsert' => true]
        );
    }

    public function addPayment(array $payload): array
    {
        $document = [
            'customer_id' => $payload['customer_id'],
            'bill_month' => $payload['bill_month'],
            'amount' => (float) ($payload['amount'] ?? 0),
            'method' => $payload['method'] ?? 'cash',
            'collector' => $payload['collector'] ?? null,
            'reference' => $payload['reference'] ?? null,
            'created_at' => new UTCDateTime(),
        ];

        $result = $this->collection('payments')->insertOne($document);
        $document['_id'] = (string) $result->getInsertedId();

        return $this->normalize($document);
    }

    public function findBillByCustomerMonth(string $customerId, string $month): ?array
    {
        $bill = $this->collection('bills')->findOne([
            'customer_id' => $customerId,
            'billing_month' => $month,
        ]);

        return $bill ? $this->normalize($bill) : null;
    }

    public function updateBillPayment(string $customerId, string $month, float $paidAmount, float $dueAmount): void
    {
        $status = $dueAmount <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid');

        $this->collection('bills')->updateOne(
            [
                'customer_id' => $customerId,
                'billing_month' => $month,
            ],
            [
                '$set' => [
                    'paid_amount' => $paidAmount,
                    'due_amount' => $dueAmount,
                    'status' => $status,
                    'updated_at' => new UTCDateTime(),
                ],
            ],
            ['upsert' => false]
        );
    }

    public function listBills(string $month = '', int $limit = 200): array
    {
        $filter = [];
        if ($month !== '') {
            $filter['billing_month'] = $month;
        }

        $cursor = $this->collection('bills')->find($filter, [
            'sort' => ['billing_month' => -1, 'customer_id' => 1],
            'limit' => $limit,
        ]);

        return array_map([$this, 'normalize'], iterator_to_array($cursor));
    }

    public function listPayments(string $customerId = '', int $limit = 200, string $month = ''): array
    {
        $filter = [];
        if ($customerId !== '') {
            $filter['customer_id'] = $customerId;
        }

        if ($month !== '') {
            $filter['bill_month'] = $month;
        }

        $cursor = $this->collection('payments')->find($filter, [
            'sort' => ['created_at' => -1],
            'limit' => $limit,
        ]);

        return array_map([$this, 'normalize'], iterator_to_array($cursor));
    }
}
