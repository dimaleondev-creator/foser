@extends('university.layout')
@section('content')
<div class="student-welcome"><div><p class="eyebrow">MESSAGERIE</p><h1>Échanges avec le FOSER</h1></div></div>
<section class="student-panel">
@forelse($threads as $thread)
    <article class="application-item"><div><strong>{{ $thread->subject ?: 'Message sans objet' }}</strong><small>{{ $thread->status }} · {{ $thread->updated_at }}</small></div><div class="thread-messages">@forelse($thread->messages as $message)<p><strong>{{ $message->sender_name }}</strong> · {{ $message->created_at }}<br>{{ $message->body }}</p>@empty<p>Aucun message.</p>@endforelse</div>@if($thread->status === 'open')<form method="POST" action="{{ route('university.messages.reply', $thread->id) }}" class="stack">@csrf<textarea name="body" placeholder="Votre réponse" maxlength="5000" required></textarea><button class="button button-dark" type="submit">Répondre</button></form>@endif</article>
@empty
    <p>Aucun échange avec le FOSER.</p>
@endforelse
{{ $threads->links() }}
</section>
@endsection
