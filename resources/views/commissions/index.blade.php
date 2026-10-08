<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mes commissions | FOSER</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="student-body"><main class="shell student-main">
    <a class="text-link" href="{{ route('home') }}">Accueil FOSER</a>
    <header class="student-welcome" style="margin-top:32px"><div><p class="eyebrow">ESPACE DÉLIBÉRATION</p><h1>Commissions</h1><p>Réunions, dossiers inscrits et décisions dont vous êtes membre.</p></div></header>
    @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
    <section class="student-panel"><div class="panel-title"><h2>Vos commissions</h2><span>{{ $commissions->total() }} au total</span></div>
        @forelse($commissions as $commission)
            <article class="application-item"><div><small>{{ $commission->program->name }} · {{ $commission->call->title }}</small><strong>{{ $commission->name }}</strong><span>{{ $commission->scheduled_at?->format('d/m/Y H:i') }} · {{ $commission->venue ?: 'Lieu à préciser' }} · {{ $commission->status }}</span></div><a class="button button-dark" href="{{ route('commissions.show', $commission) }}">Ouvrir <span aria-hidden="true">→</span></a></article>
        @empty
            <div class="student-empty">Aucune commission ne vous est attribuée.</div>
        @endforelse
        <div class="partner-pagination">{{ $commissions->links() }}</div>
    </section>
</main></body></html>