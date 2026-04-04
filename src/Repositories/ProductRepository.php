<?php

declare(strict_types=1);

namespace App\Repositories;

use MongoDB\BSON\UTCDateTime;
use MongoDB\Operation\FindOneAndUpdate;

final class ProductRepository extends BaseRepository
{
    public function list(int $limit = 100): array
    {
        $cursor = $this->collection('products')->find([], [
            'sort' => ['created_at' => -1],
            'limit' => $limit,
        ]);

        return array_map([$this, 'normalize'], iterator_to_array($cursor));
    }

    public function create(array $data): array
    {
        $payload = [
            'sku' => $data['sku'] ?? null,
            'name' => $data['name'],
            'category' => $data['category'] ?? 'general',
            'price' => (float) ($data['price'] ?? 0),
            'cost_price' => (float) ($data['cost_price'] ?? 0),
            'stock' => (int) ($data['stock'] ?? 0),
            'reorder_level' => (int) ($data['reorder_level'] ?? 0),
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
        ];

        $result = $this->collection('products')->insertOne($payload);
        $payload['_id'] = (string) $result->getInsertedId();

        return $this->normalize($payload);
    }

    public function getPriceById(string $id): ?float
    {
        $document = $this->collection('products')->findOne([
            '_id' => $this->objectId($id),
        ], [
            'projection' => ['price' => 1],
        ]);

        if ($document === null) {
            return null;
        }

        return (float) ((array) $document)['price'];
    }

    public function getPricingById(string $id): ?array
    {
        $document = $this->collection('products')->findOne([
            '_id' => $this->objectId($id),
        ], [
            'projection' => ['price' => 1, 'cost_price' => 1],
        ]);

        if ($document === null) {
            return null;
        }

        $arr = (array) $document;
        return [
            'price' => (float) ($arr['price'] ?? 0),
            'cost_price' => (float) ($arr['cost_price'] ?? 0),
        ];
    }

    public function findById(string $id): ?array
    {
        $product = $this->collection('products')->findOne([
            '_id' => $this->objectId($id),
        ]);

        return $product ? $this->normalize($product) : null;
    }

    public function decrementStock(string $id, int $quantity): bool
    {
        if ($quantity <= 0) {
            return true;
        }

        $updated = $this->collection('products')->findOneAndUpdate(
            [
                '_id' => $this->objectId($id),
                'stock' => ['$gte' => $quantity],
            ],
            [
                '$inc' => ['stock' => -$quantity],
                '$set' => ['updated_at' => new UTCDateTime()],
            ],
            [
                'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
            ]
        );

        return $updated !== null;
    }

    public function incrementStock(string $id, int $quantity): bool
    {
        if ($quantity <= 0) {
            return true;
        }

        $result = $this->collection('products')->updateOne(
            ['_id' => $this->objectId($id)],
            [
                '$inc' => ['stock' => $quantity],
                '$set' => ['updated_at' => new UTCDateTime()],
            ]
        );

        return $result->getMatchedCount() > 0;
    }

    public function updateById(string $id, array $data): bool
    {
        $allowed = ['sku', 'name', 'category', 'price', 'cost_price', 'stock', 'reorder_level'];
        $set = [];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            if (in_array($field, ['price', 'cost_price'], true)) {
                $set[$field] = (float) $data[$field];
            } elseif (in_array($field, ['stock', 'reorder_level'], true)) {
                $set[$field] = (int) $data[$field];
            } else {
                $set[$field] = $data[$field];
            }
        }

        if ($set === []) {
            return false;
        }

        $set['updated_at'] = new UTCDateTime();
        $result = $this->collection('products')->updateOne(['_id' => $this->objectId($id)], ['$set' => $set]);
        return $result->getModifiedCount() > 0;
    }

    public function deleteById(string $id): bool
    {
        $result = $this->collection('products')->deleteOne(['_id' => $this->objectId($id)]);
        return $result->getDeletedCount() > 0;
    }
}
