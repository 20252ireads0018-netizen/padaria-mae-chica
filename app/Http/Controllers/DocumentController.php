<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $q = Document::with('user');

        if ($s = $request->input('busca')) {
            $q->where('title', 'like', "%$s%");
        }
        if ($c = $request->input('categoria')) {
            $q->where('category', $c);
        }
        if ($request->input('ver') === 'vencendo') {
            $q->whereNotNull('due_date')->where('due_date', '<=', now()->addDays(15)->toDateString());
        }

        return view('documents.index', [
            'documents' => $q->orderByRaw('due_date IS NULL')->orderBy('due_date')->latest()->paginate(30)->withQueryString(),
            'dueSoon' => Document::whereNotNull('due_date')->whereBetween('due_date', [now()->toDateString(), now()->addDays(15)->toDateString()])->count(),
            'overdue' => Document::whereNotNull('due_date')->where('due_date', '<', now()->toDateString())->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'category' => ['required', Rule::in(Document::CATEGORIES)],
            'due_date' => 'nullable|date',
            'amount' => 'nullable',
            'notes' => 'nullable|string|max:1000',
            'file' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,xml,txt',
        ]);

        if ($file = $request->file('file')) {
            $data['file_path'] = $file->store('documentos');
            $data['original_name'] = $file->getClientOriginalName();
        }
        unset($data['file']);
        $data['amount'] = filled($data['amount'] ?? null) ? money_in($data['amount']) : null;

        Document::create([...$data, 'user_id' => $request->user()->id]);

        return back()->with('ok', 'Documento arquivado.');
    }

    public function download(Document $document)
    {
        abort_unless($document->file_path && Storage::exists($document->file_path), 404);

        return Storage::download($document->file_path, $document->original_name);
    }

    public function destroy(Document $document)
    {
        if ($document->file_path) {
            Storage::delete($document->file_path);
        }
        $document->delete();

        return back()->with('ok', 'Documento excluído.');
    }
}
