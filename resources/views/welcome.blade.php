<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="FOSER, Fonds de Soutien à l'Éducation et à la Recherche du Burkina Faso.">
    <meta property="og:title" content="FOSER | Éducation, recherche et innovation">
    <meta property="og:description" content="Le service public qui accompagne les parcours, les savoirs et les solutions de demain.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/') }}">
    <title>FOSER | Éducation, recherche et innovation</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body>
    <div class="topline"><div class="shell top-inner"><span>RÉPUBLIQUE DU BURKINA FASO</span><span>La Patrie ou la Mort, nous Vaincrons</span><a href="{{ url('/contact') }}">Nous contacter</a></div></div>
    <header class="site-header">
        <div class="shell nav-wrap">
            <a class="brand" href="{{ url('/') }}" aria-label="FOSER, accueil"><span class="brand-mark">F</span><span><strong>FOSER</strong><small>Fonds de Soutien à l'Éducation<br>et à la Recherche</small></span></a>
            <button class="menu-toggle" type="button" aria-label="Ouvrir le menu" aria-expanded="false">&#9776;</button>
            <nav class="main-nav" aria-label="Navigation principale">
                <a class="active" data-nav-path="/" href="{{ url('/') }}">Accueil</a><a data-nav-path="/le-foser" href="{{ route('institution.index') }}">Le FOSER</a><a data-nav-path="/programmes" href="{{ route('programs.index') }}">Nos programmes</a><a data-nav-path="/calls" href="{{ url('/calls') }}">Appels à candidatures</a><a data-nav-path="/news" href="{{ url('/news') }}">Actualités</a><a data-nav-path="/documents" href="{{ url('/documents') }}">Ressources</a><a data-nav-path="/agenda" href="{{ route('events.index') }}">Agenda</a><a data-nav-path="/researcher" href="{{ route('researcher.register') }}">Créer un compte chercheur</a><a data-nav-path="/university" href="{{ route('university.dashboard') }}">Université</a>
            </nav>
            <div class="nav-actions"><a class="lang" href="#">EN</a><a class="text-link" href="{{ route('publications.public') }}">Publications</a><a class="text-link" href="{{ route('student.register') }}">S'inscrire</a><a class="button button-dark" href="{{ route('student.login') }}">Espace étudiant <span>↗</span></a><a class="staff-link" href="{{ route('researcher.register') }}">Créer un compte chercheur</a><a class="staff-link" href="{{ route('university.dashboard') }}">Espace université</a><a class="staff-link" href="{{ url('/admin/login') }}">Agents</a></div>
        </div>
    </header>

    <main>
        @foreach($homeSliders ?? [] as $slider)
            <section class="shell section-pad" style="padding-bottom: 0;">
                <div class="notice-box">
                    <strong>{{ $slider->title }}</strong>
                    @if($slider->description)
                        <span> — {{ $slider->description }}</span>
                    @endif
                    @if($slider->button_label && $slider->button_url)
                        <a href="{{ $slider->button_url }}">{{ $slider->button_label }}</a>
                    @endif
                </div>
            </section>
        @endforeach

        <section class="hero"><div class="shell hero-content"><p class="eyebrow light">ÉDUCATION · RECHERCHE · INNOVATION</p><h1>Investir dans les talents,<br><em>transformer l'avenir.</em></h1><p class="hero-copy">Le FOSER accompagne les étudiants, les chercheurs et les institutions qui font avancer le Burkina Faso.</p><div class="hero-actions"><a class="button button-lime" href="{{ url('/programs') }}">Découvrir nos programmes <span>→</span></a><a class="text-link light" href="{{ url('/about') }}">Comprendre notre mission <span>↗</span></a></div></div><div class="hero-note"><span>01</span><span class="rule"></span><span>Notre engagement</span></div></section>

        <section class="intro shell section-pad"><div class="section-kicker"><span>01</span><span>Le FOSER en bref</span></div><div class="intro-grid"><div><h2>Donner à chaque ambition<br><em>les moyens d'aboutir.</em></h2></div><div><p class="lead">Le Fonds de Soutien à l'Éducation et à la Recherche est un instrument public dédié à l'égalité des chances et à l'émergence des savoirs.</p><a class="text-link" href="{{ url('/about') }}">En savoir plus <span>→</span></a></div></div></section>

        <section class="stats-band"><div class="shell stats-grid"><div><strong>{{ number_format($stats['students'], 0, ',', ' ') }}</strong><span>Étudiants accompagnés</span></div><div><strong>{{ number_format($stats['applications'], 0, ',', ' ') }}</strong><span>Dossiers déposés</span></div><div><strong>{{ number_format($stats['projects'], 0, ',', ' ') }}</strong><span>Projets financés</span></div><div><strong>{{ number_format($stats['universities'], 0, ',', ' ') }}</strong><span>Universités partenaires</span></div></div></section>

        <section class="programs shell section-pad"><div class="section-heading"><div><div class="section-kicker"><span>02</span><span>Nos interventions</span></div><h2>Des programmes qui<br><em>ouvrent des possibles.</em></h2></div><a class="text-link" href="{{ url('/programs') }}">Voir tous les programmes <span>→</span></a></div><div class="program-grid"><article class="program-card program-main"><span class="card-number">01</span><div><span class="card-label">Éducation</span><h3>Aides financières</h3><p>Soutenir les parcours d'études et faire de l'accès au savoir une réalité.</p><a href="{{ url('/programs') }}">Explorer <span>↗</span></a></div></article><article class="program-card program-research"><span class="card-number">02</span><div><span class="card-label">Excellence</span><h3>Recherche</h3><p>Financer les idées qui répondent aux défis de notre société.</p><a href="{{ url('/programs') }}">Explorer <span>↗</span></a></div></article><article class="program-card program-innovation"><span class="card-number">03</span><div><span class="card-label">Avenir</span><h3>Innovation</h3><p>Encourager les solutions locales et les entrepreneurs de demain.</p><a href="{{ url('/programs') }}">Explorer <span>↗</span></a></div></article></div></section>

        <section class="calls-band"><div class="shell section-pad"><div class="section-heading light-heading"><div><div class="section-kicker"><span>03</span><span>Opportunités</span></div><h2>Les appels<br><em>en cours.</em></h2></div><a class="text-link light" href="{{ route('calls.index') }}">Tous les appels <span>→</span></a></div><div class="call-list">@forelse($openCalls as $call)<a class="call-row" href="{{ route('calls.show', $call->reference) }}"><span class="call-date">{{ $call->closes_at ? \Carbon\Carbon::parse($call->closes_at)->format('d.m.Y') : 'À venir' }}</span><span><small>{{ $call->reference }}</small><strong>{{ $call->title }}</strong></span><span class="arrow">↗</span></a>@empty<div class="empty-state">Aucun appel à candidatures n'est actuellement publié.</div>@endforelse</div></div></section>

        <section class="events shell section-pad"><div class="section-heading"><div><div class="section-kicker"><span>04</span><span>Agenda</span></div><h2>Les rendez-vous<br><em>du FOSER.</em></h2></div><a class="text-link" href="{{ route('events.index') }}">Tout l'agenda <span>→</span></a></div><div class="news-grid">@forelse($events as $event)<article class="news-card"><div class="news-meta">{{ \Carbon\Carbon::parse($event->starts_at)->format('d/m/Y H:i') }}</div><h3>{{ $event->title }}</h3><p>{{ $event->venue ?: 'Lieu à préciser' }}</p><a href="{{ route('events.show', $event->slug) }}">Voir le détail <span>→</span></a></article>@empty<div class="empty-state">Les prochains événements du FOSER seront publiés ici.</div>@endforelse</div></section>

        <section class="partners shell section-pad"><div class="section-heading"><div><div class="section-kicker"><span>05</span><span>Partenaires</span></div><h2>Un réseau<br><em>engagé.</em></h2></div><a class="text-link" href="{{ route('partners.index') }}">Tous les partenaires <span>→</span></a></div><div class="news-grid">@forelse($partners as $partner)<article class="news-card">@if($partner->logo_path)<img src="{{ asset('storage/'.$partner->logo_path) }}" alt="Logo {{ $partner->name }}" loading="lazy" style="max-width:140px;max-height:60px;object-fit:contain">@endif<h3>{{ $partner->name }}</h3><p>{{ $partner->description }}</p></article>@empty<div class="empty-state">Les partenaires du FOSER seront présentés ici.</div>@endforelse</div></section>

        <section class="testimonials shell section-pad"><div class="section-heading"><div><div class="section-kicker"><span>06</span><span>Témoignages</span></div><h2>Des parcours<br><em>qui avancent.</em></h2></div><a class="text-link" href="{{ route('testimonials.index') }}">Tous les témoignages <span>→</span></a></div><div class="news-grid">@forelse($testimonials as $testimonial)<article class="news-card"><blockquote style="margin:0 0 18px;line-height:1.6">“{{ $testimonial->body }}”</blockquote><h3>{{ $testimonial->first_name }} {{ $testimonial->last_name }}</h3><p>{{ $testimonial->job_title }}@if($testimonial->organization) · {{ $testimonial->organization }}@endif</p></article>@empty<div class="empty-state">Les témoignages des bénéficiaires seront publiés ici.</div>@endforelse</div></section>

        <section class="director shell section-pad"><div class="director-copy"><div class="section-kicker"><span>07</span><span>Parole institutionnelle</span></div><blockquote>« Chaque étudiant soutenu, chaque chercheur accompagné, est une promesse de progrès pour notre pays. »</blockquote><p class="signature">Direction générale du FOSER<br><span>Ouagadougou · Burkina Faso</span></p></div></section>

        <section class="news shell section-pad"><div class="section-heading"><div><div class="section-kicker"><span>05</span><span>À la une</span></div><h2>Actualités &<br><em>vie du Fonds.</em></h2></div><a class="text-link" href="{{ route('news.index') }}">Toute l'actualité <span>→</span></a></div><div class="news-grid">@forelse($news as $article)<article class="news-card"><div class="news-meta">{{ $article->published_at ? \Carbon\Carbon::parse($article->published_at)->format('d M Y') : 'Actualité' }}</div><h3>{{ $article->title }}</h3><p>{{ $article->excerpt }}</p><a href="{{ route('news.show', $article->slug) }}">Lire l'article <span>→</span></a></article>@empty<div class="empty-state">Les prochaines actualités du FOSER seront publiées ici.</div>@endforelse</div></section>

        <section class="map-section"><div class="shell map-grid"><div><div class="section-kicker"><span>08</span><span>Notre présence</span></div><h2>Au cœur du<br><em>Burkina Faso.</em></h2><p>Une action nationale, au plus près des établissements, des communautés et des porteurs de projets.</p><a class="button button-dark" href="{{ url('/contact') }}">Nos coordonnées <span>→</span></a></div><div class="burkina-map" data-regional-map aria-label="Statistiques FOSER par région"><div class="map-outline">BF</div><div class="regional-stats" aria-live="polite"><p>Sélectionnez une région</p><select data-region-select aria-label="Région"><option value="">Chargement…</option></select><dl><div><dt>Bénéficiaires</dt><dd data-region-beneficiaries>—</dd></div><div><dt>Dossiers</dt><dd data-region-applications>—</dd></div><div><dt>Projets financés</dt><dd data-region-projects>—</dd></div></dl></div></div></div></section>
    </main>
    <footer class="footer"><div class="shell footer-main"><div class="footer-brand"><a class="brand brand-light" href="{{ url('/') }}"><span class="brand-mark">F</span><span><strong>FOSER</strong><small>Fonds de Soutien à l'Éducation<br>et à la Recherche</small></span></a><p>Construire les capacités.<br>Faire grandir les possibles.</p></div><div><h4>Navigation</h4><a href="{{ url('/about') }}">Le FOSER</a><a href="{{ url('/programs') }}">Programmes</a><a href="{{ url('/calls') }}">Appels à candidatures</a><a href="{{ url('/news') }}">Actualités</a></div><div><h4>Besoin d'aide ?</h4><a href="{{ url('/faq') }}">Questions fréquentes</a><a href="{{ url('/contact') }}">Nous contacter</a><a href="https://wa.me/22600000000">WhatsApp ↗</a></div><div><h4>Restez informé</h4><p class="footer-small">Recevez les actualités et les opportunités du FOSER.</p><form class="newsletter" method="POST" action="{{ route('newsletter.subscribe') }}">@csrf<input type="email" name="email" placeholder="Votre adresse email" aria-label="Votre adresse email" required><button type="submit">→</button></form><form method="POST" action="{{ route('newsletter.unsubscribe') }}">@csrf<input type="email" name="email" placeholder="Email à désinscrire" aria-label="Email à désinscrire" required><button type="submit">Se désinscrire</button></form></div></div><div class="shell footer-bottom"><span>© {{ date('Y') }} FOSER · Tous droits réservés</span><span>Mentions légales&nbsp;&nbsp; Politique de confidentialité</span><span>Burkina Faso</span></div></footer>
</body>
</html>
