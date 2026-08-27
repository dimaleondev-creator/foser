<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Agenda | FOSER</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body>
<header class="site-header"><div class="shell nav-wrap"><a class="brand" href="{{ route('home') }}"><span class="brand-mark">F</span><span><strong>FOSER</strong><small>Agenda institutionnel</small></span></a><a class="text-link" href="{{ route('home') }}">Accueil</a></div></header>
<main class="shell section-pad">
    <div class="section-kicker"><span>AGENDA</span><span>Rendez-vous du FOSER</span></div>
    <h1>Les événements à venir</h1>
    <form method="GET" class="student-panel form-row" style="display:grid;grid-template-columns:2fr 1fr auto;gap:16px;margin:32px 0"><label>Rechercher<input name="q" value="{{ request('q') }}" placeholder="Titre, lieu ou description"></label><label>Catégorie<select name="category"><option value="">Toutes les catégories</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>@endforeach</select></label><button class="button button-dark" type="submit">Filtrer</button></form>
    <section class="news-grid">@forelse($upcoming as $event)<article class="news-card"><span class="news-meta">{{ $event->category ?: 'Événement' }} · {{ $event->starts_at->format('d/m/Y H:i') }}</span><h2>{{ $event->title }}</h2><p>{{ $event->venue ?: 'Lieu à préciser' }}</p><a href="{{ route('events.show', $event) }}" class="text-link">Voir le détail <span>→</span></a></article>@empty<p class="empty-state">Aucun événement à venir.</p>@endforelse</section>
    @if($upcoming->hasPages())<div style="margin-top:28px">{{ $upcoming->links() }}</div>@endif
    <div class="section-kicker" style="margin-top:80px"><span>ARCHIVES</span><span>Événements passés</span></div>
    <section class="news-grid">@forelse($past as $event)<article class="news-card"><span class="news-meta">{{ $event->starts_at->format('d/m/Y') }}</span><h2>{{ $event->title }}</h2><p>{{ $event->venue ?: 'Lieu à préciser' }}</p><a href="{{ route('events.show', $event) }}" class="text-link">Voir le détail <span>→</span></a></article>@empty<p class="empty-state">Aucun événement passé.</p>@endforelse</section>
</main>
</body></html>