<?php

declare(strict_types=1);

namespace App\Repositories;

use MongoDB\BSON\UTCDateTime;

final class TicketRepository extends BaseRepository
{
    public function list(int $limit = 300): array
    {
        $cursor = $this->collection('support_tickets')->find([], [
            'sort' => ['created_at' => -1],
            'limit' => $limit,
        ]);

        return array_map([$this, 'normalize'], iterator_to_array($cursor));
    }

    public function create(array $data): array
    {
        $payload = [
            'ticket_no' => $data['ticket_no'] ?? ('TKT-' . date('YmdHis')),
            'customer_id' => $data['customer_id'] ?? null,
            'subject' => $data['subject'] ?? 'General support',
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'status' => $data['status'] ?? 'open',
            'assigned_to' => $data['assigned_to'] ?? null,
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
        ];

        $result = $this->collection('support_tickets')->insertOne($payload);
        $payload['_id'] = (string) $result->getInsertedId();

        return $this->normalize($payload);
    }

    public function updateStatus(string $id, string $status): bool
    {
        $result = $this->collection('support_tickets')->updateOne(
            ['_id' => $this->objectId($id)],
            [
                '$set' => [
                    'status' => $status,
                    'updated_at' => new UTCDateTime(),
                ],
            ]
        );

        return $result->getModifiedCount() > 0;
    }
}
