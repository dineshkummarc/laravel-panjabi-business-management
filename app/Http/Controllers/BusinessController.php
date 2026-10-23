<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\ProductionBatch;
use App\Models\Purchase;
use App\Models\Shift;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\TailoringJob;
use App\Models\Transfer;
use App\Models\Variant;
use App\Services\AttendanceImport;
use App\Services\Audit;
use App\Services\Inventory;
use App\Services\Ledger;
use App\Services\Modules;
use App\Services\Operations;
use App\Services\Orders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ImranDev\UnicodePdf\Facades\UnicodePdf;

class BusinessController extends Controller
{
    private function access(string $ability): void
    {
        abort_unless(auth()->user()->allows($ability), 403);
    }

    private function branch(int $id): Branch
    {
        $b = Branch::findOrFail($id);
        if (auth()->user()->branch_id && ! in_array(auth()->user()->role, ['owner', 'admin', 'accountant'])) {
            abort_unless($b->id === auth()->user()->branch_id, 403);
        }

return $b;
    }

    public function dashboard()
    {
        return view('business.dashboard', [
            'sales' => auth()->user()->allows('orders') || auth()->user()->allows('reports') ? Order::where('status', '!=', 'canceled')->sum('total') : null, 'pending' => auth()->user()->allows('orders') ? Order::where('status', 'pending')->count() : null,
            'stock' => auth()->user()->allows('inventory') || auth()->user()->allows('pos') ? StockLevel::sum('quantity') : null, 'products' => auth()->user()->allows('catalog') ? Product::count() : null, 'recent' => auth()->user()->allows('orders') ? Order::with('branch')->latest('id')->limit(8)->get() : collect(),
            'branches' => Branch::get(), 'lowStock' => auth()->user()->allows('inventory') ? StockLevel::with(['variant.product', 'branch'])->where('quantity', '<=', 5)->limit(8)->get() : collect(),
        ]);
    }

    public function orders()
    {
        $this->access('orders');

        return view('business.orders', ['orders' => Order::with('branch')->latest()->paginate(20)]);
    }

    public function order(Order $order)
    {
        $this->access('orders');

        return view('business.order', ['order' => $order->load(['items', 'payments', 'branch'])]);
    }

    public function status(Request $request, Order $order, Orders $service)
    {
        $this->access('orders');
        $data = $request->validate(['status' => 'required|string']);
        $service->transition($order, $data['status']);

        return back()->with('success', 'Order updated.');
    }

    public function payment(Request $request, Order $order, Orders $service)
    {
        $this->access('orders');
        $data = $request->validate(['amount' => 'required|integer|min:1', 'method' => 'required|in:cash,bank,manual_mobile,cod', 'reference' => 'required|string|max:100']);
        $service->pay($order, $data['amount'], $data['method'], 'manual:'.auth()->user()->organization_id.':'.$data['reference']);

        return back()->with('success', 'Verified payment recorded.');
    }

    public function returns(Request $request, Order $order, Orders $service)
    {
        $this->access('orders');
        $data = $request->validate(['item_id' => 'required|integer', 'quantity' => 'required|integer|min:1', 'disposition' => 'required|in:sellable,damaged', 'reference' => 'required|uuid']);
        $service->returnItem($order, $data['item_id'], $data['quantity'], $data['disposition'], $data['reference']);

        return back()->with('success', 'Return received and refund/balance adjustment posted.');
    }

    public function invoice(Order $order)
    {
        $this->access('orders');

        return view('business.invoice', ['order' => $order->load(['items', 'branch'])]);
    }

    public function pdf(Order $order)
    {
        $this->access('orders');

        return UnicodePdf::preset('bengali')->loadView('business.pdf', ['order' => $order->load(['items', 'branch', 'organization'])])->a4()->download($order->number.'.pdf');
    }

    public function pos()
    {
        $this->access('pos');
        app(Modules::class)->require('pos');

        return view('business.pos', ['variants' => Variant::whereHas('product', fn ($q) => $q->where('active', true)->where('kind', 'finished'))->with(['product', 'stocks'])->get(), 'branches' => Branch::get(), 'key' => (string) Str::uuid()]);
    }

    public function sell(Request $request, Orders $orders)
    {
        $this->access('pos');
        app(Modules::class)->require('pos');
        $data = $request->validate(['branch_id' => 'required|integer', 'customer_name' => 'required|string|max:120', 'phone' => 'required|string|max:30', 'items' => 'required|array', 'items.*' => 'nullable|integer|min:0|max:1000', 'idempotency_key' => 'required|uuid', 'cash' => 'required|integer|min:0', 'bank' => 'required|integer|min:0', 'channel' => 'required|in:pos,wholesale']);
        $this->branch($data['branch_id']);
        if (auth()->user()->role === 'cashier' && ! Shift::where('user_id', auth()->id())->where('branch_id', $data['branch_id'])->whereNull('closed_at')->exists()) {
            throw ValidationException::withMessages(['shift' => 'Open your cash shift before selling.']);
        } if ($data['channel'] === 'wholesale') {
            app(Modules::class)->require('wholesale');
        }
        $cart = array_filter($data['items'], fn ($q) => (int) $q > 0);
        $order = DB::transaction(function () use ($data, $cart, $orders) {
            if ($existing = Order::where('idempotency_key', $data['idempotency_key'])->first()) {
                return $existing;
            }
            $order = $orders->place($data, $cart, $data['idempotency_key']);
            if ($data['channel'] === 'pos' && $order->total !== $data['cash'] + $data['bank']) {
                throw ValidationException::withMessages(['cash' => 'Cash plus bank must exactly match the total in poisha.']);
            }
            if ($data['cash']) {
                $orders->pay($order, $data['cash'], 'cash', 'pos-cash:'.$order->id);
            }
            if ($data['bank']) {
                $orders->pay($order, $data['bank'], 'bank', 'pos-bank:'.$order->id);
            }

            return $order;
        });

        return redirect('/business/orders/'.$order->id)->with('success', 'Sale completed. Inventory and accounts updated.');
    }

    public function inventory()
    {
        $this->access('inventory');

        return view('business.inventory', ['stocks' => StockLevel::with(['variant.product', 'branch'])->paginate(30), 'variants' => Variant::with('product')->get(), 'branches' => Branch::get(), 'movements' => StockMovement::with(['variant', 'branch'])->latest()->limit(20)->get(), 'transfers' => Transfer::with(['variant', 'source', 'destination'])->latest()->limit(20)->get()]);
    }

    public function adjust(Request $request, Inventory $inventory, Ledger $ledger)
    {
        $this->access('inventory');
        app(Modules::class)->require('inventory');
        $d = $request->validate(['branch_id' => 'required|integer', 'variant_id' => 'required|integer', 'quantity' => 'required|integer|not_in:0', 'reason' => 'required|string|max:200']);
        $this->branch($d['branch_id']);
        $v = Variant::findOrFail($d['variant_id']);
        DB::transaction(function () use ($d, $v, $inventory, $ledger) {
            $ref = 'ADJ-'.Str::uuid();
            $inventory->move($d['branch_id'], $v->id, $d['quantity'], $d['reason'], $ref);
            $ledger->pair($d['branch_id'], $ref, 'Stock adjustment: '.$d['reason'], $d['quantity'] > 0 ? 'Inventory' : 'Stock adjustment expense', $d['quantity'] > 0 ? 'Opening equity' : 'Inventory', abs($d['quantity']) * $v->cost);
            Audit::record('inventory.adjusted', $ref, $d);
        });

        return back()->with('success', 'Stock adjustment posted.');
    }

    public function transfer(Request $request, Operations $service)
    {
        abort_unless(in_array(auth()->user()->role, ['owner', 'admin']), 403);
        app(Modules::class)->require('inventory');
        $d = $request->validate(['source_id' => 'required|integer', 'destination_id' => 'required|integer', 'variant_id' => 'required|integer', 'quantity' => 'required|integer|min:1']);
        $service->dispatch($d['source_id'], $d['destination_id'], $d['variant_id'], $d['quantity']);

        return back()->with('success', 'Stock dispatched into transit.');
    }

    public function transferReceive(Request $request, Transfer $transfer, Operations $service)
    {
        abort_unless(in_array(auth()->user()->role, ['owner', 'admin']), 403);
        $d = $request->validate(['quantity' => 'required|integer|min:1']);
        $service->receiveTransfer($transfer, $d['quantity']);

        return back()->with('success', 'Transfer receipt recorded.');
    }

    public function purchases()
    {
        $this->access('purchasing');

        return view('business.purchases', ['purchases' => Purchase::with(['supplier', 'branch'])->latest()->paginate(20), 'suppliers' => Supplier::get(), 'variants' => Variant::with('product')->get(), 'branches' => Branch::get()]);
    }

    public function purchaseCreate(Request $request)
    {
        $this->access('purchasing');
        app(Modules::class)->require('purchasing');
        $d = $request->validate(['branch_id' => 'required|integer', 'supplier_id' => 'required|integer', 'variant_id' => 'required|integer', 'quantity' => 'required|integer|min:1', 'unit_cost' => 'required|integer|min:1']);
        $this->branch($d['branch_id']);
        Supplier::findOrFail($d['supplier_id']);
        Variant::findOrFail($d['variant_id']);
        Purchase::create($d + ['reference' => 'PO-'.strtoupper(Str::random(8))]);

        return back()->with('success', 'Purchase order created.');
    }

    public function purchaseReceive(Request $request, Purchase $purchase, Operations $service)
    {
        $this->access('purchasing');
        app(Modules::class)->require('purchasing');
        $d = $request->validate(['quantity' => 'required|integer|min:1']);
        $service->receive($purchase, $d['quantity']);

        return back()->with('success', 'Goods received.');
    }

    public function purchasePay(Request $request, Purchase $purchase, Operations $service)
    {
        $this->access('purchasing');
        app(Modules::class)->require('purchasing');
        $d = $request->validate(['amount' => 'required|integer|min:1']);
        $service->paySupplier($purchase, $d['amount']);

        return back()->with('success', 'Supplier payment posted.');
    }

    public function people()
    {
        $this->access('hr');

        return view('business.people', ['employees' => Employee::with('branch')->get(), 'attendance' => Attendance::with('employee')->latest('occurred_at')->limit(30)->get(), 'payrolls' => Payroll::with('employee')->latest()->limit(20)->get()]);
    }

    public function attendance(Request $request)
    {
        $this->access('attendance');
        app(Modules::class)->require('attendance');
        $d = $request->validate(['employee_id' => 'required|integer', 'type' => 'required|in:in,out', 'occurred_at' => 'required|date', 'event_id' => 'required|string|max:100']);
        Employee::findOrFail($d['employee_id']);
        Attendance::firstOrCreate(['event_id' => $d['event_id']], $d);

        return back()->with('success', 'Attendance recorded.');
    }

    public function attendanceDemo(Request $request, AttendanceImport $service)
    {
        $this->access('attendance');
        $d = $request->validate(['employee_id' => 'required|integer']);
        $count = $service->simulate(Employee::findOrFail($d['employee_id']));

        return back()->with('success', "Imported $count new simulated device events via Attendance Hub. No hardware was contacted.");
    }

    public function payroll(Request $request, Operations $service)
    {
        $this->access('hr');
        app(Modules::class)->require('hr');
        $d = $request->validate(['employee_id' => 'required|integer', 'period' => 'required|date_format:Y-m', 'deduction' => 'required|integer|min:0']);
        $service->payroll(Employee::findOrFail($d['employee_id']), $d['period'], $d['deduction']);

        return back()->with('success', 'Payroll paid and posted.');
    }

    public function tailoring()
    {
        $this->access('tailoring');

        return view('business.tailoring', ['jobs' => TailoringJob::latest()->paginate(20), 'branches' => Branch::get()]);
    }

    public function tailoringCreate(Request $request, Operations $service)
    {
        $this->access('tailoring');
        app(Modules::class)->require('tailoring');
        $d = $request->validate(['branch_id' => 'required|integer', 'customer_name' => 'required|string|max:120', 'phone' => 'required|string|max:30', 'design' => 'required|string|max:200', 'due_date' => 'required|date', 'price' => 'required|integer|min:1', 'deposit' => 'required|integer|min:0', 'chest' => 'required|numeric|min:1|max:100', 'length' => 'required|numeric|min:1|max:100', 'sleeve' => 'required|numeric|min:1|max:100']);
        $this->branch($d['branch_id']);
        if ($d['deposit'] > $d['price']) {
            throw ValidationException::withMessages(['deposit' => 'Deposit exceeds price.']);
        }
        DB::transaction(function () use ($d, $service) {
            $measurements = array_intersect_key($d, array_flip(['chest', 'length', 'sleeve']));
            unset($d['chest'],$d['length'],$d['sleeve']);
            $job = TailoringJob::create($d + ['measurements' => $measurements]);
            if ($d['deposit']) {
                $service->tailoringPayment($job, $d['deposit']);
            }
        });

        return back()->with('success', 'Tailoring job created.');
    }

    public function tailoringUpdate(Request $request, TailoringJob $job, Operations $service)
    {
        $this->access('tailoring');
        app(Modules::class)->require('tailoring');
        if ($request->filled('amount')) {
            $d = $request->validate(['amount' => 'required|integer|min:1']);
            $service->tailoringPayment($job, $d['amount']);
        } else {
            $d = $request->validate(['status' => 'required|string']);
            $service->tailoringStatus($job, $d['status']);
        }

return back()->with('success', 'Tailoring job updated.');
    }

    public function production()
    {
        $this->access('production');

        return view('business.production', ['batches' => ProductionBatch::with(['variant.product', 'material.product', 'branch'])->latest()->paginate(20), 'branches' => Branch::get(), 'variants' => Variant::with('product')->get()]);
    }

    public function productionCreate(Request $request)
    {
        $this->access('production');
        app(Modules::class)->require('production');
        $d = $request->validate(['branch_id' => 'required|integer', 'name' => 'required|string|max:120', 'variant_id' => 'required|integer', 'material_id' => 'required|integer|different:variant_id', 'quantity' => 'required|integer|min:1', 'material_quantity' => 'required|integer|min:1', 'labor_cost' => 'required|integer|min:0']);
        $this->branch($d['branch_id']);
        Variant::findOrFail($d['variant_id']);
        Variant::findOrFail($d['material_id']);
        ProductionBatch::create($d);

        return back()->with('success', 'Production batch planned.');
    }

    public function produce(ProductionBatch $batch, Operations $service)
    {
        $this->access('production');
        app(Modules::class)->require('production');
        $service->produce($batch);

        return back()->with('success', 'Batch completed. Material consumption and finished goods posted.');
    }

    public function reports(Request $request)
    {
        $this->access('reports');
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $entries = JournalEntry::whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to);
        if ($request->filled('branch')) {
            $entries->where('branch_id', $request->integer('branch'));
        }
        $lines = JournalLine::whereIn('journal_entry_id', $entries->select('id'))->selectRaw('account, SUM(debit) as debit, SUM(credit) as credit')->groupBy('account')->get();

        return view('business.reports', compact('lines', 'from', 'to') + ['branches' => Branch::get(), 'expenses' => Expense::with('branch')->latest()->limit(15)->get()]);
    }

    public function expense(Request $request, Operations $service)
    {
        $this->access('accounting');
        app(Modules::class)->require('accounting');
        $d = $request->validate(['branch_id' => 'required|integer', 'category' => 'required|string|max:100', 'description' => 'required|string|max:200', 'amount' => 'required|integer|min:1', 'spent_at' => 'required|date']);
        $this->branch($d['branch_id']);
        $service->expense($d);

        return back()->with('success', 'Expense posted.');
    }

    public function modules()
    {
        abort_unless(auth()->user()->allows('settings'), 403);

        return view('business.modules', ['registry' => config('business.modules'), 'enabled' => auth()->user()->organization->modules]);
    }

    public function modulesUpdate(Request $request, Modules $service)
    {
        abort_unless(auth()->user()->allows('settings'), 403);
        abort_if(config('business.demo'), 403, 'Module changes are disabled in the shared demo.');
        $d = $request->validate(['modules' => 'nullable|array', 'modules.*' => 'string']);
        $service->update(auth()->user()->organization, $d['modules'] ?? []);

        return back()->with('success', 'Modules updated. History is preserved.');
    }

    public function shifts()
    {
        $this->access('shifts');

        return view('business.shifts', ['shifts' => Shift::latest()->limit(20)->get(), 'branches' => Branch::get()]);
    }

    public function shiftOpen(Request $request)
    {
        $this->access('shifts');
        app(Modules::class)->require('pos');
        $d = $request->validate(['branch_id' => 'required|integer', 'opening' => 'required|integer|min:0']);
        $this->branch($d['branch_id']);
        if (Shift::where('user_id', auth()->id())->whereNull('closed_at')->exists()) {
            throw ValidationException::withMessages(['shift' => 'Close your existing shift first.']);
        } Shift::create($d + ['user_id' => auth()->id()]);

        return back()->with('success', 'Shift opened.');
    }

    public function shiftClose(Request $request, Shift $shift)
    {
        $this->access('shifts');
        abort_unless($shift->user_id === auth()->id() || auth()->user()->allows('settings'), 403);
        $d = $request->validate(['counted' => 'required|integer|min:0']);
        DB::transaction(function () use ($shift,$d) {
            $shift = Shift::lockForUpdate()->findOrFail($shift->id);
            if ($shift->closed_at) {
                return;
            } $cash = Payment::where('shift_id',$shift->id)->sum('amount');
            $shift->update(['counted' => $d['counted'], 'expected' => $shift->opening + $cash, 'closed_at' => now()]);
        });

        return back()->with('success','Shift closed.');
    }
}
