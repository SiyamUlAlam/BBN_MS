<?php

declare(strict_types=1);

namespace App\Repositories;

use MongoDB\BSON\UTCDateTime;

final class TopologyRepository extends BaseRepository
{
    public function listZones(int $limit = 200): array
    {
        $cursor = $this->collection('zones')->find([], ['sort' => ['name' => 1], 'limit' => $limit]);
        return array_map([$this, 'normalize'], iterator_to_array($cursor));
    }

    public function createZone(array $data): array
    {
        $payload = [
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
        ];

        $result = $this->collection('zones')->insertOne($payload);
        $payload['_id'] = (string) $result->getInsertedId();

        return $this->normalize($payload);
    }

    public function listAreasByZone(string $zoneId, int $limit = 500): array
    {
        $cursor = $this->collection('areas')->find(['zone_id' => $zoneId], ['sort' => ['name' => 1], 'limit' => $limit]);
        return array_map([$this, 'normalize'], iterator_to_array($cursor));
    }

    public function createArea(array $data): array
    {
        $payload = [
            'zone_id' => $data['zone_id'],
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
        ];

        $result = $this->collection('areas')->insertOne($payload);
        $payload['_id'] = (string) $result->getInsertedId();

        return $this->normalize($payload);
    }

    public function listLineSources(int $limit = 200): array
    {
        $cursor = $this->collection('line_sources')->find([], ['sort' => ['name' => 1], 'limit' => $limit]);
        return array_map([$this, 'normalize'], iterator_to_array($cursor));
    }

    public function createLineSource(array $data): array
    {
        $payload = [
            'name' => $data['name'],
            'provider' => $data['provider'] ?? null,
            'capacity_mbps' => (float) ($data['capacity_mbps'] ?? 0),
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
        ];

        $result = $this->collection('line_sources')->insertOne($payload);
        $payload['_id'] = (string) $result->getInsertedId();

        return $this->normalize($payload);
    }

    public function listDistributionBoxes(?string $zoneId = null, int $limit = 500): array
    {
        $filter = [];
        if ($zoneId !== null && $zoneId !== '') {
            $filter['zone_id'] = $zoneId;
        }

        $cursor = $this->collection('distribution_boxes')->find($filter, ['sort' => ['name' => 1], 'limit' => $limit]);
        return array_map([$this, 'normalize'], iterator_to_array($cursor));
    }

    public function createDistributionBox(array $data): array
    {
        $payload = [
            'zone_id' => $data['zone_id'] ?? null,
            'area_id' => $data['area_id'] ?? null,
            'line_source_id' => $data['line_source_id'] ?? null,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'capacity_ports' => (int) ($data['capacity_ports'] ?? 0),
            'used_ports' => (int) ($data['used_ports'] ?? 0),
            'status' => $data['status'] ?? 'active',
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
        ];

        $result = $this->collection('distribution_boxes')->insertOne($payload);
        $payload['_id'] = (string) $result->getInsertedId();

        return $this->normalize($payload);
    }

    public function incrementUsedPort(string $distributionBoxId): void
    {
        if ($distributionBoxId === '') {
            return;
        }

        $this->collection('distribution_boxes')->updateOne(
            ['_id' => $this->objectId($distributionBoxId)],
            [
                '$inc' => ['used_ports' => 1],
                '$set' => ['updated_at' => new UTCDateTime()],
            ]
        );
    }

    public function updateZone(string $id, array $data): bool
    {
        $set = [];
        if (array_key_exists('name', $data)) {
            $set['name'] = $data['name'];
        }
        if (array_key_exists('code', $data)) {
            $set['code'] = $data['code'];
        }
        if ($set === []) {
            return false;
        }
        $set['updated_at'] = new UTCDateTime();
        $result = $this->collection('zones')->updateOne(['_id' => $this->objectId($id)], ['$set' => $set]);
        return $result->getModifiedCount() > 0;
    }

    public function deleteZone(string $id): bool
    {
        $result = $this->collection('zones')->deleteOne(['_id' => $this->objectId($id)]);
        return $result->getDeletedCount() > 0;
    }

    public function updateArea(string $id, array $data): bool
    {
        $set = [];
        if (array_key_exists('zone_id', $data)) {
            $set['zone_id'] = $data['zone_id'];
        }
        if (array_key_exists('name', $data)) {
            $set['name'] = $data['name'];
        }
        if (array_key_exists('code', $data)) {
            $set['code'] = $data['code'];
        }
        if ($set === []) {
            return false;
        }
        $set['updated_at'] = new UTCDateTime();
        $result = $this->collection('areas')->updateOne(['_id' => $this->objectId($id)], ['$set' => $set]);
        return $result->getModifiedCount() > 0;
    }

    public function deleteArea(string $id): bool
    {
        $result = $this->collection('areas')->deleteOne(['_id' => $this->objectId($id)]);
        return $result->getDeletedCount() > 0;
    }

    public function updateLineSource(string $id, array $data): bool
    {
        $set = [];
        if (array_key_exists('name', $data)) {
            $set['name'] = $data['name'];
        }
        if (array_key_exists('provider', $data)) {
            $set['provider'] = $data['provider'];
        }
        if (array_key_exists('capacity_mbps', $data)) {
            $set['capacity_mbps'] = (float) $data['capacity_mbps'];
        }
        if ($set === []) {
            return false;
        }
        $set['updated_at'] = new UTCDateTime();
        $result = $this->collection('line_sources')->updateOne(['_id' => $this->objectId($id)], ['$set' => $set]);
        return $result->getModifiedCount() > 0;
    }

    public function deleteLineSource(string $id): bool
    {
        $result = $this->collection('line_sources')->deleteOne(['_id' => $this->objectId($id)]);
        return $result->getDeletedCount() > 0;
    }

    public function updateDistributionBox(string $id, array $data): bool
    {
        $set = [];
        foreach (['zone_id', 'area_id', 'line_source_id', 'name', 'code', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $set[$field] = $data[$field];
            }
        }
        if (array_key_exists('capacity_ports', $data)) {
            $set['capacity_ports'] = (int) $data['capacity_ports'];
        }
        if (array_key_exists('used_ports', $data)) {
            $set['used_ports'] = (int) $data['used_ports'];
        }
        if ($set === []) {
            return false;
        }
        $set['updated_at'] = new UTCDateTime();
        $result = $this->collection('distribution_boxes')->updateOne(['_id' => $this->objectId($id)], ['$set' => $set]);
        return $result->getModifiedCount() > 0;
    }

    public function deleteDistributionBox(string $id): bool
    {
        $result = $this->collection('distribution_boxes')->deleteOne(['_id' => $this->objectId($id)]);
        return $result->getDeletedCount() > 0;
    }
}
