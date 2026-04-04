<?php

declare(strict_types=1);

namespace App\Repositories;

use MongoDB\BSON\UTCDateTime;

final class ConnectionRepository extends BaseRepository
{
    public function list(int $limit = 100): array
    {
        $cursor = $this->collection('connection_orders')->find([], ['sort' => ['created_at' => -1], 'limit' => $limit]);
        return array_map([$this, 'normalize'], iterator_to_array($cursor));
    }

    public function createOrder(array $payload): array
    {
        $payload['created_at'] = new UTCDateTime();
        $payload['updated_at'] = new UTCDateTime();

        $result = $this->collection('connection_orders')->insertOne($payload);
        $payload['_id'] = (string) $result->getInsertedId();

        return $this->normalize($payload);
    }

    public function listByCustomerId(string $customerId, int $limit = 50): array
    {
        $cursor = $this->collection('connection_orders')->find([
            'customer_id' => $customerId,
        ], [
            'sort' => ['created_at' => -1],
            'limit' => $limit,
        ]);

        return array_map([$this, 'normalize'], iterator_to_array($cursor));
    }

    public function findById(string $id): ?array
    {
        $order = $this->collection('connection_orders')->findOne(['_id' => $this->objectId($id)]);
        return $order ? $this->normalize($order) : null;
    }

    public function updateById(string $id, array $data): bool
    {
        $allowed = [
            'customer_id',
            'technician',
            'line_source_id',
            'distribution_box_id',
            'service_charge',
            'status',
            'connected_on',
        ];

        $set = [];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            if ($field === 'service_charge') {
                $set[$field] = (float) $data[$field];
            } else {
                $set[$field] = $data[$field];
            }
        }

        if ($set === []) {
            return false;
        }

        $set['updated_at'] = new UTCDateTime();
        $result = $this->collection('connection_orders')->updateOne(['_id' => $this->objectId($id)], ['$set' => $set]);
        return $result->getModifiedCount() > 0;
    }

    public function deleteById(string $id): bool
    {
        $result = $this->collection('connection_orders')->deleteOne(['_id' => $this->objectId($id)]);
        return $result->getDeletedCount() > 0;
    }
}
