<?php

declare(strict_types=1);

namespace App\Repositories;

use MongoDB\BSON\UTCDateTime;

final class UserRepository extends BaseRepository
{
    public function findByUsername(string $username): ?array
    {
        $user = $this->collection('users')->findOne(['username' => $username]);
        return $user ? $this->normalize($user) : null;
    }

    public function list(int $limit = 200): array
    {
        $cursor = $this->collection('users')->find([], [
            'sort' => ['created_at' => -1],
            'limit' => $limit,
            'projection' => ['password_hash' => 0],
        ]);

        return array_map([$this, 'normalize'], iterator_to_array($cursor));
    }

    public function create(array $data): array
    {
        $payload = [
            'full_name' => $data['full_name'] ?? 'User',
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'role' => $data['role'] ?? 'staff',
            'status' => $data['status'] ?? 'active',
            'password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
        ];

        $result = $this->collection('users')->insertOne($payload);
        $payload['_id'] = (string) $result->getInsertedId();
        unset($payload['password_hash']);

        return $this->normalize($payload);
    }
}
