@extends('university.layout')
@section('content')
<div class="student-welcome"><div><p class="eyebrow">MESSAGERIE</p><h1>Échanges avec le FOSER</h1></div></div>
<section class="student-panel">
@forelse($threads as $thread)
    <article class="application-item"><div><strong>{{ $thread->subject ?: 'Message sans objet' }}</strong><small>{{ $thread->status }} · {{ $thread->updated_at }}</small></div><form method="POST" action="{{ route('university.messages.reply', $thread->id) }}" class="stack">@csrf<textarea name="body" placeholder="Votre réponse" required></textarea><button class="button button-dark" type="submit">Répondre</button></form></article>
@empty
    <p>Aucun échange avec le FOSER.</p>
@endforelse
{{ $threads->links() }}
</section>
@endsection
