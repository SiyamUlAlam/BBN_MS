<?php

declare(strict_types=1);

namespace App\Repositories;

use MongoDB\BSON\UTCDateTime;

final class FinanceRepository extends BaseRepository
{
    public function addIncome(array $payload): array
    {
        $document = [
            'date' => $payload['date'] ?? date('Y-m-d'),
            'source' => $payload['source'] ?? 'other',
            'category' => $payload['category'] ?? 'general',
            'amount' => (float) ($payload['amount'] ?? 0),
            'note' => $payload['note'] ?? null,
            'created_at' => new UTCDateTime(),
        ];

        $result = $this->collection('income_entries')->insertOne($document);
        $document['_id'] = (string) $result->getInsertedId();

        return $this->normalize($document);
    }

    public function addExpense(array $payload): array
    {
        $document = [
            'date' => $payload['date'] ?? date('Y-m-d'),
            'category' => $payload['category'] ?? 'general',
            'amount' => (float) ($payload['amount'] ?? 0),
            'note' => $payload['note'] ?? null,
            'created_at' => new UTCDateTime(),
        ];

        $result = $this->collection('expense_entries')->insertOne($document);
        $document['_id'] = (string) $result->getInsertedId();

        return $this->normalize($document);
    }

    public function listIncome(string $month = '', int $limit = 300): array
    {
        $filter = [];
        if ($month !== '') {
            $filter['date'] = ['$regex' => '^' . preg_quote($month, '/')];
        }

        $cursor = $this->collection('income_entries')->find($filter, ['sort' => ['date' => -1], 'limit' => $limit]);
        return array_map([$this, 'normalize'], iterator_to_array($cursor));
    }

    public function listExpense(string $month = '', int $limit = 300): array
    {
        $filter = [];
        if ($month !== '') {
            $filter['date'] = ['$regex' => '^' . preg_quote($month, '/')];
        }

        $cursor = $this->collection('expense_entries')->find($filter, ['sort' => ['date' => -1], 'limit' => $limit]);
        return array_map([$this, 'normalize'], iterator_to_array($cursor));
    }

    public function monthlySummary(string $month): array
    {
        $income = $this->listIncome($month, 10000);
        $expense = $this->listExpense($month, 10000);

        $incomeTotal = array_reduce($income, fn (float $carry, array $item): float => $carry + (float) ($item['amount'] ?? 0), 0.0);
        $expenseTotal = array_reduce($expense, fn (float $carry, array $item): float => $carry + (float) ($item['amount'] ?? 0), 0.0);

        return [
            'month' => $month,
            'income_total' => round($incomeTotal, 2),
            'expense_total' => round($expenseTotal, 2),
            'net' => round($incomeTotal - $expenseTotal, 2),
            'income_count' => count($income),
            'expense_count' => count($expense),
        ];
    }
}
