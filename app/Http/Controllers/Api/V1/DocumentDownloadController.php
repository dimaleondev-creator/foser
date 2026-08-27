<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Document;
use App\Models\Download;
use Illuminate\Http\Request;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController
{
    public function __invoke(Request $request, Document $document): StreamedResponse
    {
        abort_unless($document->status === 'published', 404);
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->path), 404);

        Download::create([
            'document_id' => $document->getKey(),
            'user_id' => $request->user()->getAuthIdentifier(),
            'ip_address' => $request->ip(),
        ]);

        $extension = pathinfo($document->path, PATHINFO_EXTENSION) ?: 'pdf';
        return $disk->download($document->path, basename($document->title) . '.' . $extension);
    }
}
