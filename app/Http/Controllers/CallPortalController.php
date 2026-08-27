<?php

namespace App\Http\Controllers;

use App\Models\Call;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CallPortalController extends Controller
{
    public function index(): View
    {
        $query = Call::query()->whereIn('status', ['published', 'suspended', 'closed']);
        if ($search = request('q')) {
            $query->where(fn ($builder) => $builder->where('title', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('domains', 'like', "%{$search}%"));
        }
        if ($program = request('program')) {
            $query->where('program_id', $program);
        }
        if ($status = request('status')) {
            $query->where('status', $status);
        }
        if ($year = request('year')) {
            $query->whereYear('opens_at', $year);
        }
        if ($date = request('date')) {
            $query->whereDate('opens_at', '<=', $date)->whereDate('closes_at', '>=', $date);
        }

        return view('calls.index', [
            'calls' => $query->latest()->paginate(12)->withQueryString(),
            'programs' => DB::table('programs')->where('status', 'published')->orderBy('name')->get(),
        ]);
    }

    public function show(Call $call): View
    {
        abort_unless(in_array($call->status, ['published', 'suspended', 'closed'], true), 404);
        return view('calls.show', ['call' => $call, 'documents' => DB::table('call_documents')->join('documents', 'documents.id', '=', 'call_documents.document_id')->where('call_id', $call->id)->where('documents.status', 'published')->where('documents.visibility', 'public')->select('documents.*', 'call_documents.label', 'call_documents.is_required')->get(), 'requiredDocuments' => collect(explode("\n", (string) $call->required_documents))->filter()]);
    }

    public function results(Call $call): View
    {
        abort_unless($call->status === 'closed' && $call->results_published_at, 404);
        return view('calls.results', ['call' => $call, 'results' => DB::table('application_results')->join('applications', 'applications.id', '=', 'application_results.application_id')->where('applications.call_id', $call->id)->whereNotNull('application_results.published_at')->select('applications.reference', 'application_results.decision', 'application_results.score', 'application_results.reason')->get()]);
    }

    public function downloadDocument(string $call, string $document): StreamedResponse
    {
        $record = DB::table('call_documents')->join('calls', 'calls.id', '=', 'call_documents.call_id')->join('documents', 'documents.id', '=', 'call_documents.document_id')->where('call_documents.call_id', $call)->where('call_documents.document_id', $document)->whereIn('calls.status', ['published', 'suspended', 'closed'])->where('documents.status', 'published')->where('documents.visibility', 'public')->first();
        abort_unless($record, 404);
        abort_unless(Storage::disk($record->disk)->exists($record->path), 404);
        DB::table('downloads')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'document_id' => $record->document_id,
            'user_id' => Auth::id(),
            'ip_address' => request()->ip(),
            'downloaded_at' => now(),
        ]);

        return response()->streamDownload(
            fn () => print Storage::disk($record->disk)->get($record->path),
            basename($record->title),
        );
    }
}
