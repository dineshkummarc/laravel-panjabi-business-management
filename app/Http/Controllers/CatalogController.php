<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use App\Models\Variant;
use App\Services\Audit;
use App\Services\CatalogCsv;
use App\Services\Modules;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    private function access(): void
    {
        abort_unless(auth()->user()->allows('catalog'), 403);
    }

    public function imports()
    {
        $this->access();

        return view('business.imports', ['preview' => session('catalog_preview')]);
    }

    public function preview(Request $r, CatalogCsv $csv)
    {
        $this->access();
        app(Modules::class)->require('inventory');
        $r->validate(['file' => 'required|file|max:1024|mimes:csv,txt']);
        $preview = $csv->preview($r->file('file')->getRealPath());
        $preview['organization_id'] = auth()->user()->organization_id;
        session(['catalog_preview' => $preview]);

        return back();
    }

    public function commit(CatalogCsv $csv)
    {
        $this->access();
        $p = session('catalog_preview');
        abort_unless($p && $p['organization_id'] === auth()->user()->organization_id && ! $p['errors'] && count($p['rows']), 422);
        $count = $csv->commit($p['rows']);
        session()->forget('catalog_preview');

        return back()->with('success', "Imported $count variants as unpublished products. Review and publish them in the catalog.");
    }

    public function export()
    {
        $this->access();

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, CatalogCsv::COLUMNS, ',', '"', '');
            Variant::with('product')->orderBy('id')->chunkById(100, function ($variants) use ($out) {
                foreach ($variants as $v) {
                    $values = [$v->product->name, $v->product->slug, $v->sku, $v->size, $v->color, $v->price, $v->cost, $v->product->fabric];
                    $values = array_map(fn ($value) => preg_match('/^[=+@\-\t\r]/', (string) $value) ? "'".$value : $value, $values);
                    fputcsv($out, $values, ',', '"', '');
                }
            });
            fclose($out);
        }, 'catalog.csv', ['Content-Type' => 'text/csv']);
    }

    public function staff()
    {
        abort_unless(auth()->user()->allows('settings'), 403);

        return view('business.staff', ['users' => User::where('organization_id', auth()->user()->organization_id)->get(), 'branches' => Branch::get()]);
    }

    public function staffCreate(Request $r)
    {
        abort_unless(auth()->user()->allows('settings'), 403);
        abort_if(config('business.demo'), 403, 'Staff access changes are disabled in the shared demo.');
        $d = $r->validate(['name' => 'required|string|max:120', 'email' => 'required|email|unique:users', 'password' => 'required|string|min:12', 'role' => 'required|in:admin,manager,cashier,inventory,accountant,production,hr', 'branch_id' => 'nullable|integer']);
        if (! empty($d['branch_id'])) {
            Branch::findOrFail($d['branch_id']);
        }
        if (in_array($d['role'], ['manager', 'cashier']) && empty($d['branch_id'])) {
            return back()->withErrors(['branch_id' => 'Managers and cashiers require an assigned branch.']);
        }
        User::create($d + ['organization_id' => auth()->user()->organization_id]);
        Audit::record('staff.created', $d['email'], ['role' => $d['role'], 'branch_id' => $d['branch_id'] ?? null]);

        return back()->with('success','Staff account created.');
    }
}
