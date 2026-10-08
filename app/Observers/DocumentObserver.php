<?php

namespace App\Observers;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Filesystem\FilesystemAdapter;

class DocumentObserver
{
    public function saving(Document $document): void
    {
        if (Auth::check() && (! $document->exists || $document->isDirty('status') || $document->isDirty('visibility'))
            && ($document->status === 'published' || $document->visibility === 'public')) {
            Gate::authorize('documents.validate');
        }

        $this->moveDocumentOffPublicDisk($document);

        if (! $document->exists) {
            $document->uploaded_by ??= Auth::id();
        }

        if (Str::startsWith($document->path, 'official-documents/')) {
            $document->disk = 'local';
        }

        if ($document->document_date && ($document->isDirty('document_date') || ! $document->year)) {
            $document->year = $document->document_date->year;
        }
    }

    public function created(Document $document): void
    {
        $this->syncFileMetadata($document);
        $this->recordVersion($document);
        app(AuditLogger::class)->record('document.created', 'documents', (string) $document->getKey(), [], $document->getAttributes());
    }

    public function updated(Document $document): void
    {
        $changes = $document->getChanges();
        $oldValues = [];
        foreach (array_keys($changes) as $field) {
            $oldValues[$field] = $document->getRawOriginal($field);
        }

        if (array_key_exists('path', $changes)) {
            $this->syncFileMetadata($document);
            $this->recordVersion($document);
        }

        app(AuditLogger::class)->record('document.updated', 'documents', (string) $document->getKey(), $oldValues, array_intersect_key($document->getAttributes(), $changes));
    }

    public function deleted(Document $document): void
    {
        app(AuditLogger::class)->record('document.deleted', 'documents', (string) $document->getKey(), $document->only(['title', 'status', 'visibility']), []);
    }

    private function syncFileMetadata(Document $document): void
    {
        if (! config('filesystems.disks.'.$document->disk)) {
            return;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($document->disk ?: 'local');
        if (! $document->path || ! $disk->exists($document->path)) {
            return;
        }

        $document->forceFill([
            'mime_type' => $disk->mimeType($document->path),
            'size' => $disk->size($document->path),
            'checksum' => hash('sha256', $disk->get($document->path)),
        ])->saveQuietly();
    }

    private function moveDocumentOffPublicDisk(Document $document): void
    {
        if ($document->disk !== 'public' && ! $document->versions()->where('disk', 'public')->exists()) {
            return;
        }

        $source = Storage::disk('public');
        $target = Storage::disk('local');
        $relocatedPaths = [];

        foreach ($document->versions()->where('disk', 'public')->get() as $version) {
            $privatePath = $this->movePathToPrivateDisk($version->path, $source, $target);
            $relocatedPaths[$version->path] = $privatePath;
            $version->forceFill(['disk' => 'local', 'path' => $privatePath])->saveQuietly();
        }

        if ($document->disk === 'public') {
            $document->path = $relocatedPaths[$document->path] ?? $this->movePathToPrivateDisk($document->path, $source, $target);
            $document->disk = 'local';
        }
    }

    private function movePathToPrivateDisk(?string $path, FilesystemAdapter $source, FilesystemAdapter $target): ?string
    {
        if (! $path || ! $source->exists($path)) {
            return $path;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'bin';
        $privatePath = 'official-documents/'.Str::uuid().'.'.$extension;
        $stream = $source->readStream($path);
        abort_unless(is_resource($stream), 500, 'Le document privé ne peut pas être lu depuis son stockage actuel.');

        try {
            $stored = $target->put($privatePath, $stream);
        } finally {
            fclose($stream);
        }

        if (! $stored) {
            abort(500, 'Le document privé ne peut pas être déplacé vers son stockage sécurisé.');
        }

        if (! $source->delete($path)) {
            $target->delete($privatePath);
            abort(500, 'La copie publique du document privé ne peut pas être supprimée.');
        }

        return $privatePath;
    }

    private function recordVersion(Document $document): void
    {
        if (! $document->path) {
            return;
        }

        $version = ((int) DocumentVersion::query()->where('document_id', $document->getKey())->max('version')) + 1;
        DocumentVersion::create([
            'document_id' => $document->getKey(),
            'disk' => $document->disk,
            'created_by' => Auth::id(),
            'version' => $version,
            'path' => $document->path,
            'checksum' => $document->checksum,
            'size' => $document->size,
        ]);
    }
}