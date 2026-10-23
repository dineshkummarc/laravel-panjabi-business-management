<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Payroll;
use App\Models\ProductionBatch;
use App\Models\Purchase;
use App\Models\TailoringJob;
use App\Models\Transfer;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Operations
{
    public function __construct(private Inventory $inventory, private Ledger $ledger) {}

    public function receive(Purchase $purchase, int $quantity): void
    {
        DB::transaction(function () use ($purchase, $quantity) {
            $purchase = Purchase::lockForUpdate()->findOrFail($purchase->id);
            if ($quantity < 1 || $quantity > $purchase->quantity - $purchase->received) {
                throw ValidationException::withMessages(['quantity' => 'Receipt exceeds the outstanding quantity.']);
            }
            $this->inventory->move($purchase->branch_id, $purchase->variant_id, $quantity, 'purchase', $purchase->reference);
            $standard = Variant::findOrFail($purchase->variant_id)->cost * $quantity;
            $actual = $quantity * $purchase->unit_cost;
            $this->ledger->post($purchase->branch_id, 'receipt:'.$purchase->id.':'.($purchase->received + $quantity), 'Purchase receipt', [
                ['account' => 'Inventory', 'debit' => $standard, 'credit' => 0], ['account' => 'Accounts payable', 'debit' => 0, 'credit' => $actual],
                ['account' => 'Purchase variance', 'debit' => max(0, $actual - $standard), 'credit' => max(0, $standard - $actual)],
            ]);
            $purchase->update(['received' => $purchase->received + $quantity, 'status' => $purchase->received + $quantity === $purchase->quantity ? 'received' : 'partial']);
            Audit::record('purchase.received', $purchase->reference, ['quantity' => $quantity]);
        });
    }

    public function paySupplier(Purchase $purchase, int $amount): void
    {
        DB::transaction(function () use ($purchase, $amount) {
            $purchase = Purchase::lockForUpdate()->findOrFail($purchase->id);
            if ($amount < 1 || $amount > $purchase->received * $purchase->unit_cost - $purchase->paid) {
                throw ValidationException::withMessages(['amount' => 'Payment exceeds the received supplier balance.']);
            }
            $this->ledger->pair($purchase->branch_id, 'supplier-pay:'.$purchase->id.':'.($purchase->paid + $amount), 'Supplier payment', 'Accounts payable', 'Cash & bank', $amount);
            $purchase->increment('paid', $amount);
        });
    }

    public function dispatch(int $source, int $destination, int $variant, int $quantity): Transfer
    {
        return DB::transaction(function () use ($source, $destination, $variant, $quantity) {
            if ($source === $destination || $quantity < 1) {
                throw ValidationException::withMessages(['transfer' => 'Choose distinct branches and a positive quantity.']);
            }
            Branch::findOrFail($source);
            Branch::findOrFail($destination);
            $transfer = Transfer::create(['source_id' => $source, 'destination_id' => $destination, 'variant_id' => $variant, 'quantity' => $quantity]);
            $this->inventory->move($source, $variant, -$quantity, 'transfer dispatch', 'TR-'.$transfer->id);
            $cost = Variant::findOrFail($variant)->cost * $quantity;
            $this->ledger->pair($source, 'transfer-out:'.$transfer->id, 'Transfer dispatched', 'Inventory in transit', 'Inventory', $cost);

            return $transfer;
        });
    }

    public function receiveTransfer(Transfer $transfer, int $quantity): void
    {
        DB::transaction(function () use ($transfer, $quantity) {
            $transfer = Transfer::lockForUpdate()->findOrFail($transfer->id);
            if ($quantity < 1 || $quantity > $transfer->quantity - $transfer->received) {
                throw ValidationException::withMessages(['quantity' => 'Receipt exceeds in-transit quantity.']);
            }
            $this->inventory->move($transfer->destination_id, $transfer->variant_id, $quantity, 'transfer receipt', 'TR-'.$transfer->id);
            $this->ledger->pair($transfer->destination_id, 'transfer-in:'.$transfer->id.':'.($transfer->received + $quantity), 'Transfer received', 'Inventory', 'Inventory in transit', Variant::findOrFail($transfer->variant_id)->cost * $quantity);
            $transfer->update(['received' => $transfer->received + $quantity, 'status' => $transfer->received + $quantity === $transfer->quantity ? 'received' : 'partial']);
        });
    }

    public function produce(ProductionBatch $batch): void
    {
        DB::transaction(function () use ($batch) {
            $batch = ProductionBatch::lockForUpdate()->findOrFail($batch->id);
            if ($batch->status === 'completed') {
                return;
            }
            $material = Variant::findOrFail($batch->material_id);
            $output = Variant::findOrFail($batch->variant_id);
            if ($material->product->kind !== 'material' || $output->product->kind !== 'finished') {
                throw ValidationException::withMessages(['production' => 'Select raw material and a finished product.']);
            }
            $this->inventory->move($batch->branch_id, $material->id, -$batch->material_quantity, 'production issue', 'BATCH-'.$batch->id);
            $this->inventory->move($batch->branch_id, $output->id, $batch->quantity, 'production receipt', 'BATCH-'.$batch->id);
            $input = $material->cost * $batch->material_quantity;
            $standard = $output->cost * $batch->quantity;
            $actual = $input + $batch->labor_cost;
            $this->ledger->post($batch->branch_id, 'production:'.$batch->id, 'Production completed', [
                ['account' => 'Inventory', 'debit' => $standard, 'credit' => 0], ['account' => 'Inventory', 'debit' => 0, 'credit' => $input], ['account' => 'Cash & bank', 'debit' => 0, 'credit' => $batch->labor_cost],
                ['account' => 'Production variance', 'debit' => max(0, $actual - $standard), 'credit' => max(0, $standard - $actual)],
            ]);
            $batch->update(['status' => 'completed']);
            Audit::record('production.completed', 'BATCH-'.$batch->id);
        });
    }

    public function payroll(Employee $employee, string $period, int $deduction): Payroll
    {
        return DB::transaction(function () use ($employee, $period, $deduction) {
            if ($existing = Payroll::where('employee_id', $employee->id)->where('period', $period)->first()) {
                return $existing;
            }
            if ($deduction < 0 || $deduction > $employee->salary) {
                throw ValidationException::withMessages(['deduction' => 'Invalid deduction.']);
            }
            $payroll = Payroll::create(['employee_id' => $employee->id, 'period' => $period, 'gross' => $employee->salary, 'deduction' => $deduction, 'net' => $employee->salary - $deduction]);
            $this->ledger->pair($employee->branch_id, 'payroll:'.$payroll->id, 'Payroll '.$period, 'Salary expense', 'Cash & bank', $payroll->net);
            Audit::record('payroll.paid', (string) $payroll->id);

            return $payroll;
        });
    }

    public function expense(array $data): Expense
    {
        return DB::transaction(function () use ($data) {
            Branch::findOrFail($data['branch_id']);
            $expense = Expense::create($data);
            $this->ledger->pair($expense->branch_id, 'expense:'.$expense->id, $expense->description, 'Operating expense', 'Cash & bank', $expense->amount);

            return $expense;
        });
    }

    public function tailoringPayment(TailoringJob $job, int $amount): void
    {
        DB::transaction(function () use ($job, $amount) {
            $job = TailoringJob::lockForUpdate()->findOrFail($job->id);
            if ($amount < 1 || $amount > $job->price - $job->collected) {
                throw ValidationException::withMessages(['amount' => 'Payment exceeds tailoring balance.']);
            }
            $this->ledger->pair($job->branch_id, 'tailoring-pay:'.$job->id.':'.($job->collected + $amount), 'Tailoring payment', 'Cash & bank', 'Customer advances', $amount);
            $job->increment('collected', $amount);
        });
    }

    public function tailoringStatus(TailoringJob $job, string $status): void
    {
        DB::transaction(function () use ($job, $status) {
            $job = TailoringJob::lockForUpdate()->findOrFail($job->id);
            $next = ['received' => 'cutting', 'cutting' => 'sewing', 'sewing' => 'fitting', 'fitting' => 'ready', 'ready' => 'delivered'];
            if (($next[$job->status] ?? null) !== $status) {
                throw ValidationException::withMessages(['status' => 'Invalid tailoring stage.']);
            }
            if ($status === 'delivered') {
                if ($job->collected !== $job->price) {
                    throw ValidationException::withMessages(['status' => 'Collect the remaining balance before handover.']);
                }
                $this->ledger->pair($job->branch_id,'tailoring-sale:'.$job->id,'Tailoring handover','Customer advances','Tailoring revenue',$job->price);
            }
            $job->update(['status' => $status]);
        });
    }
}
