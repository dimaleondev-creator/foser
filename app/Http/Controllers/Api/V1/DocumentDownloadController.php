<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Document;
use App\Models\Download;
use App\Services\DocumentAccessService;
use Illuminate\Http\Request;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController
{
    public function __invoke(Request $request, Document $document, DocumentAccessService $access): StreamedResponse
    {
        $document = $access->scope(Document::query(), $request->user())->whereKey($document->getKey())->firstOrFail();
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->path), 404);

        Download::create([
            'document_id' => $document->getKey(),
            'user_id' => $request->user()->getAuthIdentifier(),
            'ip_address' => $request->ip(),
        ]);

        $extension = pathinfo($document->path, PATHINFO_EXTENSION) ?: 'pdf';
        $filename = \Illuminate\Support\Str::slug(pathinfo($document->title, PATHINFO_FILENAME)).'.'.$extension;

        return $disk->download($document->path, $filename, ['X-Content-Type-Options' => 'nosniff']);
    }
}
