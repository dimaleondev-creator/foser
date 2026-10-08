<div class="space-y-4">
    <section class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
        <p class="text-sm text-gray-500">{{ $contact->name }} · {{ $contact->email }} @if($contact->phone)· {{ $contact->phone }}@endif</p>
        <p class="mt-3 whitespace-pre-line">{{ $contact->message }}</p>
        <p class="mt-3 text-xs text-gray-500">{{ $contact->created_at?->format('d/m/Y H:i') }} · {{ $contact->status }}</p>
    </section>
    @forelse($contact->replies as $reply)
        <section class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
            <p class="text-sm font-semibold">Réponse FOSER · {{ $reply->sender?->name ?: 'Équipe FOSER' }}</p>
            <p class="mt-2 whitespace-pre-line">{{ $reply->body }}</p>
            <p class="mt-2 text-xs text-gray-500">{{ $reply->sent_at?->format('d/m/Y H:i') ?: $reply->created_at?->format('d/m/Y H:i') }} · {{ $reply->delivery_status }}</p>
        </section>
    @empty
        <p class="text-sm text-gray-500">Aucune réponse enregistrée.</p>
    @endforelse
</div>
