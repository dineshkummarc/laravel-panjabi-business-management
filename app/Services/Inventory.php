<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Inventory
{
    public function move(int $branchId, int $variantId, int $quantity, string $reason, string $reference): void
    {
        DB::transaction(function () use ($branchId, $variantId, $quantity, $reason, $reference) {
            Branch::findOrFail($branchId);
            Variant::findOrFail($variantId);
            if (! $quantity) {
                return;
            }
            StockLevel::firstOrCreate(['branch_id' => $branchId, 'variant_id' => $variantId], ['quantity' => 0]);
            $query = StockLevel::where('branch_id', $branchId)->where('variant_id', $variantId);
            if ($quantity < 0) {
                $query->where('quantity', '>=', -$quantity);
            }
            $changed = $query->increment('quantity', $quantity);
            if (! $changed) {
                throw ValidationException::withMessages(['stock' => 'Insufficient stock for the selected variant and branch.']);
            }
            StockMovement::create(['branch_id' => $branchId, 'variant_id' => $variantId, 'quantity' => $quantity, 'reason' => $reason, 'reference' => $reference]);
        });
    }
}
