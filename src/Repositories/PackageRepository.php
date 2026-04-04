<?php

declare(strict_types=1);

namespace App\Repositories;

use MongoDB\BSON\UTCDateTime;

final class PackageRepository extends BaseRepository
{
    public function list(int $limit = 200): array
    {
        $cursor = $this->collection('packages')->find([], ['sort' => ['monthly_price' => 1], 'limit' => $limit]);
        return array_map([$this, 'normalize'], iterator_to_array($cursor));
    }

    public function create(array $data): array
    {
        $payload = [
            'name' => $data['name'],
            'speed_mbps' => (int) ($data['speed_mbps'] ?? 0),
            'monthly_price' => (float) ($data['monthly_price'] ?? 0),
            'installation_charge' => (float) ($data['installation_charge'] ?? 0),
            'status' => $data['status'] ?? 'active',
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
        ];

        $result = $this->collection('packages')->insertOne($payload);
        $payload['_id'] = (string) $result->getInsertedId();

        return $this->normalize($payload);
    }

    public function findById(string $id): ?array
    {
        $package = $this->collection('packages')->findOne([
            '_id' => $this->objectId($id),
        ]);

        return $package ? $this->normalize($package) : null;
    }

    public function updateById(string $id, array $data): bool
    {
        $allowed = ['name', 'speed_mbps', 'monthly_price', 'installation_charge', 'status'];
        $set = [];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            if ($field === 'speed_mbps') {
                $set[$field] = (int) $data[$field];
            } elseif (in_array($field, ['monthly_price', 'installation_charge'], true)) {
                $set[$field] = (float) $data[$field];
            } else {
                $set[$field] = $data[$field];
            }
        }

        if ($set === []) {
            return false;
        }

        $set['updated_at'] = new UTCDateTime();
        $result = $this->collection('packages')->updateOne(['_id' => $this->objectId($id)], ['$set' => $set]);
        return $result->getModifiedCount() > 0;
    }

    public function deleteById(string $id): bool
    {
        $result = $this->collection('packages')->deleteOne(['_id' => $this->objectId($id)]);
        return $result->getDeletedCount() > 0;
    }
}
