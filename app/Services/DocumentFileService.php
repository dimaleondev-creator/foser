<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DocumentFileService
{
    public function deletePermanently(Document $document): void
    {
        Gate::authorize('documents.delete');

        foreach ($document->versions as $version) {
            $versionDisk = Storage::disk($version->disk ?: $document->disk);
            if ($versionDisk->exists($version->path) && ! $versionDisk->delete($version->path)) {
                abort(500, 'Le fichier documentaire n’a pas pu être supprimé.');
            }
        }

        $documentDisk = Storage::disk($document->disk);
        if ($document->path && $documentDisk->exists($document->path) && ! $documentDisk->delete($document->path)) {
            abort(500, 'Le fichier documentaire n’a pas pu être supprimé.');
        }

        $document->forceDelete();
    }
}