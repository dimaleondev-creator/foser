<div class="space-y-3">
    @forelse ($versions as $version)
        <div class="flex items-center justify-between gap-4 border-b border-gray-200 py-3 dark:border-white/10">
            <div>
                <p class="font-medium">Version {{ $version->version }}</p>
                <p class="text-sm text-gray-500">{{ $version->created_at?->format('d/m/Y H:i') }} · {{ number_format(($version->size ?? 0) / 1048576, 2) }} Mo</p>
            </div>
            <div class="flex items-center gap-4">
                <p class="text-xs text-gray-500">{{ $version->checksum ? substr($version->checksum, 0, 12) : '' }}</p>
                <a href="{{ route('admin.documents.versions.download', [$version->document_id, $version->id]) }}" class="text-sm font-medium text-primary-600">Télécharger</a>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500">Aucune version enregistrée.</p>
    @endforelse
</div>