<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;

abstract class BaseRepository
{
    protected function collection(string $name): Collection
    {
        return Database::connection()->selectCollection($name);
    }

    protected function objectId(string $id): ObjectId
    {
        return new ObjectId($id);
    }

    protected function normalize(array|object $document): array
    {
        if ($document instanceof BSONDocument || $document instanceof BSONArray) {
            $arr = $document->getArrayCopy();
        } else {
            $arr = (array) $document;
        }

        foreach ($arr as $key => $value) {
            $arr[$key] = $this->normalizeValue($value);
        }

        return $arr;
    }

    protected function normalizeArray(array $payload): array
    {
        foreach ($payload as $key => $value) {
            $payload[$key] = $this->normalizeValue($value);
        }

        return $payload;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof ObjectId) {
            return (string) $value;
        }

        if ($value instanceof UTCDateTime) {
            return $value->toDateTime()->format(DATE_ATOM);
        }

        if ($value instanceof BSONDocument || $value instanceof BSONArray) {
            return $this->normalizeArray($value->getArrayCopy());
        }

        if (is_array($value)) {
            return $this->normalizeArray($value);
        }

        return $value;
    }
}
