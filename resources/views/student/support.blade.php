<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Aide et messages | FOSER</title>@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'))) @vite(['resources/css/app.css', 'resources/js/app.js']) @endif</head><body class="student-body"><header class="student-header"><div class="shell nav-wrap"><a class="brand" href="{{ url('/') }}"><x-site-logo-mark /><span><strong>FOSER</strong><small>Espace &eacute;tudiant</small></span></a><nav class="student-nav"><a href="{{ route('student.dashboard') }}">Tableau de bord</a><a href="{{ route('student.applications') }}">Mes dossiers</a><a href="{{ route('student.documents') }}">Documents</a><a class="active" href="{{ route('student.support') }}">Aide</a></nav><form method="POST" action="{{ route('auth.logout') }}">@csrf<button class="button button-dark" type="submit">D&eacute;connexion</button></form></div></header><main class="shell student-main"><div class="student-welcome"><div><p class="eyebrow">ASSISTANCE</p><h1>Une question ?</h1><p>&Eacute;changez avec les &eacute;quipes autoris&eacute;es et suivez vos r&eacute;clamations.</p></div></div>@if(session('status'))<div class="notice">{{ session('status') }}</div>@endif<div class="student-grid"><section class="student-panel form-panel"><h2>Nouvelle r&eacute;clamation</h2><form method="POST" action="{{ route('student.claims.create') }}">@csrf<label>Objet<input name="subject" required></label><label>Votre message<textarea name="description" rows="5" required></textarea></label><button class="button button-dark" type="submit">Cr&eacute;er la r&eacute;clamation <span>→</span></button></form></section><section class="student-panel form-panel"><h2>&Eacute;crire &agrave; un agent</h2><form method="POST" action="{{ route('student.messages.create') }}">@csrf<label>Objet<input name="subject"></label><label>Message<textarea name="body" rows="5" required></textarea></label><button class="button button-dark" type="submit">Envoyer le message <span>→</span></button></form></section></div><section class="student-panel"><div class="panel-title"><h2>Mes r&eacute;clamations</h2></div>@forelse($claims as $claim)<div class="application-item"><small>{{ $claim->reference }}</small><strong>{{ $claim->subject }}</strong><span>{{ ucfirst($claim->status) }}</span></div>@empty<div class="student-empty">Aucune r&eacute;clamation.</div>@endforelse</section><section class="student-panel"><div class="panel-title"><h2>Mes &eacute;changes</h2></div>@forelse($threads as $thread)
<article class="application-item">
    <div><strong>{{ $thread->subject ?: 'Message sans objet' }}</strong><small>{{ ucfirst($thread->status) }} · {{ \Carbon\Carbon::parse($thread->updated_at)->format('d/m/Y à H:i') }}</small></div>
    <div class="thread-messages">
        @forelse($thread->messages as $message)
            <p><strong>{{ $message->sender_name }}</strong><small> · {{ \Carbon\Carbon::parse($message->created_at)->format('d/m/Y à H:i') }}</small><br>{{ $message->body }}</p>
        @empty
            <p>Aucun message dans cet &eacute;change.</p>
        @endforelse
    </div>
    @if($thread->status === 'open')
        <form method="POST" action="{{ route('student.messages.reply', $thread->id) }}">
            @csrf
            <label>Votre r&eacute;ponse<textarea name="body" rows="3" maxlength="5000" required></textarea></label>
            <button class="button button-dark" type="submit">R&eacute;pondre <span>→</span></button>
        </form>
    @endif
</article>
@empty
    <div class="student-empty">Aucun &eacute;change.</div>
@endforelse</section></main></body></html>
