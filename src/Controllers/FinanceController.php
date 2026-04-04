<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\FinanceRepository;

final class FinanceController
{
    public function __construct(private readonly FinanceRepository $finance = new FinanceRepository())
    {
    }

    public function addIncome(Request $request): void
    {
        if (!is_array($request->body) || !isset($request->body['amount'])) {
            Response::json(['status' => 'error', 'message' => 'amount is required'], 422);
            return;
        }

        $income = $this->finance->addIncome($request->body);
        Response::json(['status' => 'success', 'data' => $income], 201);
    }

    public function addExpense(Request $request): void
    {
        if (!is_array($request->body) || !isset($request->body['amount'])) {
            Response::json(['status' => 'error', 'message' => 'amount is required'], 422);
            return;
        }

        $expense = $this->finance->addExpense($request->body);
        Response::json(['status' => 'success', 'data' => $expense], 201);
    }

    public function listIncome(Request $request): void
    {
        $month = (string) ($request->query['month'] ?? '');
        Response::json(['status' => 'success', 'data' => $this->finance->listIncome($month)]);
    }

    public function listExpense(Request $request): void
    {
        $month = (string) ($request->query['month'] ?? '');
        Response::json(['status' => 'success', 'data' => $this->finance->listExpense($month)]);
    }

    public function monthlySummary(Request $request): void
    {
        $month = (string) ($request->query['month'] ?? date('Y-m'));
        Response::json(['status' => 'success', 'data' => $this->finance->monthlySummary($month)]);
    }
}
