<?php

namespace App\Http\Controllers;

use App\Models\CatalogDraft;
use App\Services\AiCatalog;
use App\Services\Modules;
use Illuminate\Http\Request;

class AiController extends Controller
{
    private function access(): void
    {
        abort_unless(auth()->user()->allows('catalog'), 403);
    }

    public function index()
    {
        $this->access();

        return view('business.ai', ['drafts' => CatalogDraft::latest()->paginate(10)]);
    }

    public function generate(Request $r, AiCatalog $ai)
    {
        $this->access();
        $d = $r->validate(['source' => 'required|string|max:1000', 'mode' => 'required|in:keyword,url', 'locale' => 'required|in:en,bn', 'rights' => 'accepted']);
        if ($d['mode'] === 'url') {
            $r->validate(['source' => 'url:https']);
        } $ai->draft($d['source'], $d['mode'], $d['locale']);

        return back()->with('success', 'Draft generated. Review it before approval.');
    }

    public function approve(Request $r, CatalogDraft $draft, AiCatalog $ai)
    {
        $this->access();
        app(Modules::class)->require('ai');
        $d = $r->validate(['title' => 'required|string|max:150', 'description' => 'required|string|max:10000']);
        $product = $ai->approve($draft, $d['title'], $d['description']);

        return redirect('/admin/products/'.$product->id.'/edit')->with('success', 'Unpublished product created. Add variants before publishing.');
    }
}
