<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProductRepository;

final class ConnectionCostService
{
    public function __construct(private readonly ProductRepository $products = new ProductRepository())
    {
    }

    public function preview(array $items, float $serviceCharge = 0.0): array
    {
        $normalizedItems = [];
        $productsTotal = 0.0;

        foreach ($items as $item) {
            $productId = (string) ($item['product_id'] ?? '');
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($productId === '' || $quantity <= 0) {
                continue;
            }

            $pricing = $this->products->getPricingById($productId) ?? ['price' => 0.0, 'cost_price' => 0.0];
            $unitPrice = (float) ($pricing['price'] ?? 0.0);
            $unitCost = (float) ($pricing['cost_price'] ?? 0.0);
            $lineTotal = $unitPrice * $quantity;
            $productsTotal += $lineTotal;

            $normalizedItems[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'unit_cost' => $unitCost,
                'line_total' => $lineTotal,
            ];
        }

        $grandTotal = $productsTotal + $serviceCharge;

        return [
            'items' => $normalizedItems,
            'products_total' => round($productsTotal, 2),
            'service_charge' => round($serviceCharge, 2),
            'grand_total' => round($grandTotal, 2),
        ];
    }
}
