<?php

declare(strict_types=1);

namespace App\Repositories;

use MongoDB\BSON\UTCDateTime;

final class CustomerRepository extends BaseRepository
{
    public function isCustomerIdTaken(string $customerId): bool
    {
        if ($customerId === '') {
            return false;
        }

        return $this->collection('customers')->countDocuments([
            'customer_id' => $customerId,
        ]) > 0;
    }

    public function isPhoneTaken(string $phone, string $excludeCustomerId = ''): bool
    {
        if ($phone === '') {
            return false;
        }

        $filter = ['phone' => $phone];
        if ($excludeCustomerId !== '') {
            $filter['customer_id'] = ['$ne' => $excludeCustomerId];
        }

        return $this->collection('customers')->countDocuments($filter) > 0;
    }

    public function list(int $limit = 50): array
    {
        $cursor = $this->collection('customers')->find([], [
            'sort' => ['created_at' => -1],
            'limit' => $limit,
        ]);

        return array_map([$this, 'normalize'], iterator_to_array($cursor));
    }

    public function create(array $data): array
    {
        $payload = [
            'customer_id' => $data['customer_id'],
            'full_name' => $data['full_name'],
            'phone' => isset($data['phone']) && trim((string) $data['phone']) !== '' ? trim((string) $data['phone']) : null,
            'email' => $data['email'] ?? null,
            'nid' => $data['nid'] ?? null,
            'address' => $data['address'] ?? null,
            'zone_id' => $data['zone_id'] ?? null,
            'area_id' => $data['area_id'] ?? null,
            'package_id' => $data['package_id'] ?? null,
            'monthly_bill_amount' => (float) ($data['monthly_bill_amount'] ?? 0),
            'due_amount' => (float) ($data['due_amount'] ?? 0),
            'line_source_id' => $data['line_source_id'] ?? null,
            'distribution_box_id' => $data['distribution_box_id'] ?? null,
            'connection_items' => is_array($data['connection_items'] ?? null) ? array_values($data['connection_items']) : [],
            'status' => $data['status'] ?? 'pending_connection',
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
        ];

        $result = $this->collection('customers')->insertOne($payload);
        $payload['_id'] = (string) $result->getInsertedId();

        return $this->normalize($payload);
    }

    public function updateDue(string $customerId, float $amount): void
    {
        $this->collection('customers')->updateOne(
            ['customer_id' => $customerId],
            [
                '$set' => [
                    'due_amount' => $amount,
                    'updated_at' => new UTCDateTime(),
                ],
            ]
        );
    }

    public function setStatus(string $customerId, string $status): void
    {
        $this->collection('customers')->updateOne(
            ['customer_id' => $customerId],
            [
                '$set' => [
                    'status' => $status,
                    'updated_at' => new UTCDateTime(),
                ],
            ]
        );
    }

    public function findByCustomerId(string $customerId): ?array
    {
        $customer = $this->collection('customers')->findOne(['customer_id' => $customerId]);
        return $customer ? $this->normalize($customer) : null;
    }

    public function updateByCustomerId(string $customerId, array $data): bool
    {
        $allowed = [
            'full_name', 'phone', 'email', 'nid', 'address', 'zone_id', 'area_id', 'package_id',
            'monthly_bill_amount', 'due_amount', 'line_source_id', 'distribution_box_id', 'connection_items', 'status',
        ];

        $set = [];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            if (in_array($field, ['monthly_bill_amount', 'due_amount'], true)) {
                $set[$field] = (float) $data[$field];
                continue;
            }

            if ($field === 'connection_items') {
                $set[$field] = is_array($data[$field]) ? array_values($data[$field]) : [];
                continue;
            }

            if ($field === 'phone') {
                $set[$field] = trim((string) $data[$field]) !== '' ? trim((string) $data[$field]) : null;
                continue;
            }

            $set[$field] = $data[$field];
        }

        if ($set === []) {
            return false;
        }

        $set['updated_at'] = new UTCDateTime();
        $result = $this->collection('customers')->updateOne(['customer_id' => $customerId], ['$set' => $set]);

        return $result->getModifiedCount() > 0;
    }

    public function deleteByCustomerId(string $customerId): bool
    {
        $result = $this->collection('customers')->deleteOne(['customer_id' => $customerId]);
        return $result->getDeletedCount() > 0;
    }
}
