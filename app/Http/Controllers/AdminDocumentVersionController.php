<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDocumentVersionController
{
    public function __invoke(Document $document, DocumentVersion $version): StreamedResponse
    {
        abort_unless(Gate::any(['documents.update', 'documents.validate']), 403);
        abort_unless($version->document_id === $document->getKey(), 404);
        $linkedToOperationalRecord = DB::table('application_documents')->where('document_id', $document->getKey())->exists()
            || DB::table('research_project_documents')->where('document_id', $document->getKey())->exists()
            || DB::table('research_publications')->where('document_id', $document->getKey())->exists()
            || DB::table('research_conventions')->where('document_id', $document->getKey())->exists()
            || DB::table('call_documents')->where('document_id', $document->getKey())->exists()
            || DB::table('researcher_profiles')->where('cv_document_id', $document->getKey())->exists();
        abort_unless(! $linkedToOperationalRecord, 404);
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($version->disk ?: $document->disk);
        abort_unless($disk->exists($version->path), 404);

        $extension = pathinfo($version->path, PATHINFO_EXTENSION) ?: 'bin';
        $filename = str($document->title)->slug().'-v'.$version->version.'.'.$extension;

        return $disk->download($version->path, $filename, ['X-Content-Type-Options' => 'nosniff']);
    }
}