<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ __('home.description') }}">
    <meta property="og:title" content="FOSER | Éducation, recherche et innovation">
    <meta property="og:description" content="{{ __('home.og_description') }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/') }}">
    <title>{{ __('home.title') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="home-page">
    <a class="home-skip-link" href="#contenu">Aller au contenu</a>
    <div class="topline"><div class="shell top-inner"><span>RÉPUBLIQUE DU BURKINA FASO</span><span>La Patrie ou la Mort, nous Vaincrons</span><a href="{{ route('contact') }}">{{ __('home.contact') }} <span aria-hidden="true">↗</span></a></div></div>

    <header class="site-header">
        <div class="shell nav-wrap">
            <a class="brand brand-logo-only" href="{{ route('home') }}" aria-label="FOSER, accueil">
                <img class="brand-logo" src="{{ asset('images/logo-foser.png') }}" alt="FOSER" width="112" height="64">
                <span class="brand-tagline">Fonds de Soutien à l'Éducation<br>et à la Recherche</span>
            </a>
            <button class="menu-toggle" type="button" aria-label="{{ __('home.menu_open') }}" aria-expanded="false" aria-controls="home-navigation"><span aria-hidden="true">☰</span></button>
            <nav class="main-nav" id="home-navigation" aria-label="{{ __('home.nav_label') }}">
                <a class="active" data-nav-path="/" href="{{ route('home') }}">Accueil</a>
                <a data-nav-path="/le-foser" href="{{ route('institution.index') }}">Le FOSER</a>
                <a data-nav-path="/programmes" href="{{ route('programs.index') }}">Programmes</a>
                <a data-nav-path="/news" href="{{ route('news.index') }}">Actualités</a>
                <a data-nav-path="/calls" href="{{ route('calls.index') }}">Appels</a>
                <a data-nav-path="/publications" href="{{ route('publications.public') }}">Résultats</a>
                <a data-nav-path="/contact" href="{{ route('contact') }}">Contact</a>
                <a class="nav-login" href="{{ route('student.login') }}">Connexion</a>
                <a class="nav-register" href="{{ route('student.register') }}">Inscription <span aria-hidden="true">↗</span></a>
                <a class="nav-language" href="{{ route('language.switch', app()->getLocale() === 'fr' ? 'en' : 'fr') }}" aria-label="{{ app()->getLocale() === 'fr' ? 'Switch to English' : 'Passer en français' }}">{{ app()->getLocale() === 'fr' ? 'EN' : 'FR' }}</a>
            </nav>
        </div>
    </header>

    <main id="contenu">
        @if(count($homeSliders ?? []))
            <aside class="home-announcements" aria-label="Annonces du FOSER">
                <div class="shell announcement-list">
                    @foreach($homeSliders as $slider)
                        <div class="announcement-item"><span class="announcement-label">À la une</span><p><strong>{{ $slider->title }}</strong>@if($slider->description) <span>{{ $slider->description }}</span>@endif</p>@if($slider->button_label && $slider->button_url)<a href="{{ $slider->button_url }}">{{ $slider->button_label }} <span aria-hidden="true">→</span></a>@endif</div>
                    @endforeach
                </div>
            </aside>
        @endif

        <section class="hero" aria-labelledby="hero-title">
            <div class="shell hero-layout">
                <div class="hero-content">
                    <p class="eyebrow">Portail officiel · Burkina Faso</p>
                    <h1 id="hero-title">Financer l'avenir du Burkina Faso <em>par l'éducation et la recherche.</em></h1>
                    <p class="hero-copy">Le FOSER accompagne les étudiants, les chercheurs et les institutions pour faire grandir les savoirs et les solutions qui transforment le pays.</p>
                    <div class="hero-actions"><a class="button button-lime" href="{{ route('programs.index') }}">Découvrir nos programmes <span aria-hidden="true">↗</span></a><a class="hero-secondary" href="{{ route('calls.index') }}">Voir les appels à candidatures <span aria-hidden="true">→</span></a></div>
                    <div class="hero-trust"><span class="trust-mark" aria-hidden="true">BF</span><span>Un engagement national pour le savoir et l'avenir.</span></div>
                </div>
                <div class="hero-visual" aria-label="Le FOSER, au service de l'éducation et de la recherche au Burkina Faso">
                    <div class="hero-visual-top"><span>ÉDUCATION</span><span>RECHERCHE</span><span>INNOVATION</span></div>
                    <div class="hero-emblem"><img src="{{ asset('images/logo-foser.png') }}" alt="" width="168" height="96"></div>
                    <div class="hero-visual-bottom"><span class="visual-index">01</span><span class="visual-rule"></span><span>Les talents d'aujourd'hui<br>construisent le Burkina de demain.</span></div>
                    <span class="hero-orbit hero-orbit-one" aria-hidden="true"></span><span class="hero-orbit hero-orbit-two" aria-hidden="true"></span>
                </div>
            </div>
            <div class="hero-foot"><span>Fonds de Soutien à l'Éducation et à la Recherche</span><span>Ouagadougou · Burkina Faso</span></div>
        </section>

        <section class="intro shell section-pad" aria-labelledby="intro-title">
            <div class="section-kicker"><span>01</span><span>Le FOSER en bref</span></div>
            <div class="intro-grid"><h2 id="intro-title">Donner à chaque ambition <em>les moyens d'aboutir.</em></h2><div><p class="lead">Le Fonds de Soutien à l'Éducation et à la Recherche investit dans les parcours, les savoirs et les solutions de demain, partout au Burkina Faso.</p><a class="text-link" href="{{ route('institution.index') }}">Découvrir l'institution <span aria-hidden="true">→</span></a></div></div>
        </section>

        <section class="quick-access shell" aria-labelledby="quick-title">
            <div class="quick-heading"><div><p class="section-kicker"><span>02</span><span>Accès direct</span></p><h2 id="quick-title">Un portail, <em>plusieurs parcours.</em></h2></div><p>Retrouvez rapidement les espaces et les ressources qui vous concernent.</p></div>
            <div class="quick-grid">
                <a class="quick-card" href="{{ route('student.login') }}"><span class="quick-icon" aria-hidden="true">01</span><span class="quick-title">Étudiants</span><span class="quick-description">Accédez à votre espace et suivez vos démarches.</span><span class="quick-arrow" aria-hidden="true">↗</span></a>
                <a class="quick-card" href="{{ route('researcher.login') }}"><span class="quick-icon" aria-hidden="true">02</span><span class="quick-title">Chercheurs</span><span class="quick-description">Explorez les opportunités et votre espace recherche.</span><span class="quick-arrow" aria-hidden="true">↗</span></a>
                <a class="quick-card" href="{{ route('partners.index') }}"><span class="quick-icon" aria-hidden="true">03</span><span class="quick-title">Universités</span><span class="quick-description">Découvrez le réseau des partenaires institutionnels.</span><span class="quick-arrow" aria-hidden="true">↗</span></a>
                <a class="quick-card" href="{{ route('programs.category', 'aides-financieres') }}"><span class="quick-icon" aria-hidden="true">04</span><span class="quick-title">Aides financières</span><span class="quick-description">Consultez les dispositifs d'accompagnement disponibles.</span><span class="quick-arrow" aria-hidden="true">↗</span></a>
                <a class="quick-card" href="{{ route('programs.category', 'recherche') }}"><span class="quick-icon" aria-hidden="true">05</span><span class="quick-title">Doctorants & chercheurs</span><span class="quick-description">Accédez aux programmes de soutien à la recherche.</span><span class="quick-arrow" aria-hidden="true">↗</span></a>
                <a class="quick-card" href="{{ route('calls.index') }}"><span class="quick-icon" aria-hidden="true">06</span><span class="quick-title">Appels à candidatures</span><span class="quick-description">Consultez les appels publiés et leurs conditions.</span><span class="quick-arrow" aria-hidden="true">↗</span></a>
            </div>
        </section>

        <section class="stats-band" aria-label="Chiffres clés du FOSER"><div class="shell stats-grid">
            @foreach(['students' => 'Profils étudiants', 'applications' => 'Dossiers enregistrés', 'projects' => 'Projets de recherche financés', 'universities' => 'Universités actives', 'programs' => 'Programmes publiés', 'calls' => 'Appels publiés'] as $key => $label)
                <div class="stat-card"><strong class="stat-value" data-count="{{ (int) ($stats[$key] ?? 0) }}">{{ number_format((int) ($stats[$key] ?? 0), 0, ',', ' ') }}</strong><span>{{ $label }}</span></div>
            @endforeach
        </div></section>

        <section class="programs shell section-pad" id="programmes" aria-labelledby="programs-title">
            <div class="section-heading"><div><div class="section-kicker"><span>03</span><span>Nos interventions</span></div><h2 id="programs-title">Des programmes qui <em>ouvrent des possibles.</em></h2></div><a class="text-link" href="{{ route('programs.index') }}">Tous les programmes <span aria-hidden="true">→</span></a></div>
            <div class="program-grid">@forelse($programs as $index => $program)
                <article class="program-card program-card-{{ $index + 1 }}"><div class="program-card-top"><span class="card-number">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span><span class="program-type">{{ str_replace('_', ' ', $program->type) }}</span></div><div class="program-card-copy"><span class="program-status">Publié</span><h3>{{ $program->name }}</h3><p>{{ $program->description ?: 'Découvrez les objectifs, les conditions et les modalités de ce programme.' }}</p><a href="{{ route('programs.index') }}">En savoir plus <span aria-hidden="true">↗</span></a></div></article>
            @empty<div class="empty-state">Les programmes publiés seront présentés ici dès leur mise en ligne.</div>@endforelse</div>
        </section>

        <section class="calls-band" id="appels" aria-labelledby="calls-title"><div class="shell section-pad">
            <div class="section-heading light-heading"><div><div class="section-kicker"><span>04</span><span>Opportunités en cours</span></div><h2 id="calls-title">Appels à <em>candidatures.</em></h2></div><a class="text-link light" href="{{ route('calls.index') }}">Tous les appels <span aria-hidden="true">→</span></a></div>
            <div class="call-list">@forelse($openCalls as $call)
                <article class="call-row"><span class="call-status"><span aria-hidden="true"></span>Ouvert</span><div class="call-information"><span class="call-program">{{ $call->program_name }} · {{ $call->reference }}</span><h3>{{ $call->title }}</h3><p>Du {{ \Carbon\Carbon::parse($call->opens_at)->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($call->closes_at)->format('d/m/Y') }}@if(isset($call->places) && $call->places) <span>· {{ $call->places }} places</span>@endif</p></div><div class="call-actions"><a class="call-action" href="{{ route('calls.show', $call->id) }}" aria-label="Voir l'appel {{ $call->title }}">Voir l'appel <span aria-hidden="true">↗</span></a><a class="call-apply" href="{{ route('student.login', ['call_id' => $call->id]) }}">Candidater</a></div></article>
            @empty<div class="empty-state">Aucun appel n'est ouvert actuellement. Consultez les appels publiés pour connaître les prochaines échéances.</div>@endforelse</div>
        </div></section>

        <section class="news shell section-pad" id="actualites" aria-labelledby="news-title">
            <div class="section-heading"><div><div class="section-kicker"><span>05</span><span>À la une</span></div><h2 id="news-title">Actualités & <em>vie du Fonds.</em></h2></div><a class="text-link" href="{{ route('news.index') }}">Toutes les actualités <span aria-hidden="true">→</span></a></div>
            <div class="news-grid">@forelse($news as $article)
                <article class="news-card"><div class="news-card-art">@if($article->image_path)<img src="{{ asset('storage/'.$article->image_path) }}" alt="Illustration : {{ $article->title }}" loading="lazy" decoding="async">@else<span aria-hidden="true">FOSER</span><span class="news-art-line" aria-hidden="true"></span><span aria-hidden="true">{{ $loop->iteration }}</span>@endif</div><div class="news-meta"><span>{{ $article->category?->name ?: 'Actualité' }}</span><time datetime="{{ \Carbon\Carbon::parse($article->published_at)->toDateString() }}">{{ \Carbon\Carbon::parse($article->published_at)->format('d M Y') }}</time></div><h3>{{ $article->title }}</h3><p>{{ $article->excerpt }}</p><a href="{{ route('news.show', $article->slug) }}">Lire la suite <span aria-hidden="true">→</span></a></article>
            @empty<div class="empty-state">Les prochaines actualités du FOSER seront publiées ici.</div>@endforelse</div>
        </section>

        <section class="director shell section-pad" aria-labelledby="director-title">
            <figure class="director-photo">@if($directorPhoto)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($directorPhoto) }}" alt="Portrait de Dr Tiertou Edwige DEMBÉLÉ/SOMÉ, Directrice Générale du FOSER" loading="lazy">@else<div class="director-photo-placeholder" aria-hidden="true"><span>FOSER</span><small>Direction Générale</small></div>@endif<figcaption>Direction Générale · Ouagadougou</figcaption></figure>
            <div class="director-copy"><div class="section-kicker"><span>06</span><span>Mot de la Directrice Générale</span></div><h2 id="director-title" class="visually-hidden">Une mission au service de chaque talent</h2><blockquote>« Donner à chaque jeune Burkinabè les moyens de construire son avenir par l'éducation et la recherche. »</blockquote><p class="signature">Dr Tiertou Edwige DEMBÉLÉ/SOMÉ<br><span>Directrice Générale du FOSER</span></p><a class="text-link" href="{{ route('institution.section', 'direction-generale') }}">Découvrir la Direction Générale <span aria-hidden="true">→</span></a></div>
        </section>

        <section class="publications-band" aria-labelledby="publications-title"><div class="shell publication-layout"><div><div class="section-kicker"><span>07</span><span>Transparence & ressources</span></div><h2 id="publications-title">Les décisions et les savoirs <em>en partage.</em></h2></div><div class="publication-links"><a href="{{ route('calls.index') }}"><span>Appels et résultats publiés</span><span aria-hidden="true">↗</span></a><a href="{{ route('publications.public') }}"><span>Publications scientifiques</span><span aria-hidden="true">↗</span></a><a href="{{ route('documents.index') }}"><span>Documents & communiqués</span><span aria-hidden="true">↗</span></a></div></div></section>

        <section class="events shell section-pad" aria-labelledby="events-title">
            <div class="section-heading"><div><div class="section-kicker"><span>08</span><span>À venir</span></div><h2 id="events-title">L'agenda <em>du FOSER.</em></h2></div><a class="text-link" href="{{ route('events.index') }}">Tout l'agenda <span aria-hidden="true">→</span></a></div>
            <div class="event-list">@forelse($events as $event)
                <article class="event-row"><time class="event-date" datetime="{{ \Carbon\Carbon::parse($event->starts_at)->toIso8601String() }}"><span>{{ \Carbon\Carbon::parse($event->starts_at)->format('d') }}</span><span>{{ \Carbon\Carbon::parse($event->starts_at)->translatedFormat('M Y') }}</span></time><div><h3>{{ $event->title }}</h3><p>{{ $event->venue ?: 'Lieu à préciser' }} · {{ \Carbon\Carbon::parse($event->starts_at)->format('H:i') }}</p>@if($event->description)<p class="event-description">{{ \Illuminate\Support\Str::limit($event->description, 140) }}</p>@endif</div><a href="{{ route('events.show', $event->slug) }}" aria-label="Voir l'événement {{ $event->title }}">Voir <span aria-hidden="true">↗</span></a></article>
            @empty<div class="empty-state">Les prochains événements du FOSER seront annoncés ici.</div>@endforelse</div>
        </section>

        <section class="testimonials shell section-pad" aria-labelledby="testimonials-title">
            <div class="section-heading"><div><div class="section-kicker"><span>09</span><span>Paroles de bénéficiaires</span></div><h2 id="testimonials-title">Des parcours <em>qui avancent.</em></h2></div><a class="text-link" href="{{ route('testimonials.index') }}">Tous les témoignages <span aria-hidden="true">→</span></a></div>
            <div class="testimonial-grid">@forelse($testimonials as $testimonial)
                <article class="testimonial-card"><span class="quote-mark" aria-hidden="true">“</span><blockquote>{{ $testimonial->body }}</blockquote><div class="testimonial-person">@if($testimonial->photo_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($testimonial->photo_path) }}" alt="" loading="lazy">@else<span class="person-initial" aria-hidden="true">{{ mb_substr($testimonial->first_name, 0, 1) }}</span>@endif<div><strong>{{ $testimonial->first_name }} {{ $testimonial->last_name }}</strong><span>{{ $testimonial->job_title }}@if($testimonial->organization) · {{ $testimonial->organization }}@endif</span></div></div></article>
            @empty<div class="empty-state">Les témoignages des bénéficiaires seront publiés ici.</div>@endforelse</div>
        </section>

        @if(count($partners))
            <section class="partners shell section-pad" aria-labelledby="partners-title"><div class="section-heading"><div><div class="section-kicker"><span>10</span><span>Ensemble</span></div><h2 id="partners-title">Nos partenaires <em>engagés.</em></h2></div><a class="text-link" href="{{ route('partners.index') }}">Tous les partenaires <span aria-hidden="true">→</span></a></div><div class="partner-strip">@foreach($partners as $partner)<div class="partner-logo">@if($partner->logo_path)<img src="{{ asset('storage/'.$partner->logo_path) }}" alt="Logo {{ $partner->name }}" loading="lazy">@else<span>{{ $partner->name }}</span>@endif</div>@endforeach</div></section>
        @endif

        <section class="map-section" aria-labelledby="map-title"><div class="shell map-grid"><div><div class="section-kicker"><span>11</span><span>Notre présence</span></div><h2 id="map-title">Au cœur du <em>Burkina Faso.</em></h2><p>Une action nationale, au plus près des établissements, des communautés et des porteurs de projets.</p><a class="button button-dark" href="{{ route('contact') }}">Nous contacter <span aria-hidden="true">→</span></a></div><div class="burkina-map" data-regional-map aria-label="Statistiques FOSER par région"><div class="map-identity" aria-hidden="true"><span>BF</span><small>PRÉSENCE NATIONALE</small></div><div class="regional-stats" aria-live="polite"><p>Consulter les données régionales</p><label class="visually-hidden" for="home-region-select">Région</label><select id="home-region-select" data-region-select><option value="">Chargement…</option></select><dl><div><dt>Bénéficiaires</dt><dd data-region-beneficiaries>—</dd></div><div><dt>Dossiers</dt><dd data-region-applications>—</dd></div><div><dt>Projets financés</dt><dd data-region-projects>—</dd></div></dl></div></div></div></section>

        <section class="final-cta"><div class="shell final-cta-inner"><div><p class="eyebrow">Construisons ensemble</p><h2>Construisons ensemble l'avenir de <em>l'éducation et de la recherche.</em></h2></div><div class="final-cta-actions"><a class="button button-lime" href="{{ route('programs.index') }}">Découvrir les programmes <span aria-hidden="true">↗</span></a><a class="final-cta-link" href="{{ route('calls.index') }}">Consulter les appels <span aria-hidden="true">→</span></a><a class="final-cta-link" href="{{ route('student.login') }}">Se connecter <span aria-hidden="true">→</span></a></div></div></section>
    </main>

    <footer class="footer">
        <x-site-partners-footer />
        <div class="shell footer-main">
            <div class="footer-brand"><a class="brand brand-light brand-logo-only" href="{{ route('home') }}"><img class="brand-logo" src="{{ asset('images/logo-foser.png') }}" alt="FOSER" width="112" height="64"><span class="brand-tagline">Fonds de Soutien à l'Éducation<br>et à la Recherche</span></a><p>Construire les capacités.<br>Faire grandir les possibles.</p></div>
            <div><h2>Le FOSER</h2><a href="{{ route('institution.index') }}">À propos</a><a href="{{ route('institution.section', 'missions') }}">Missions</a><a href="{{ route('institution.section', 'organigramme') }}">Organisation</a><a href="{{ route('contact') }}">Contact</a></div>
            <div><h2>Programmes</h2><a href="{{ route('programs.category', 'aides-financieres') }}">Aides financières</a><a href="{{ route('programs.category', 'prets-etudes') }}">Prêts d'études</a><a href="{{ route('programs.category', 'recherche') }}">Recherche</a><a href="{{ route('programs.category', 'innovation') }}">Innovation</a></div>
            <div><h2>Candidatures</h2><a href="{{ route('calls.index') }}">Appels & résultats publiés</a><a href="{{ route('publications.public') }}">Publications scientifiques</a><a href="{{ route('student.login') }}">Suivi de candidature</a><a href="{{ route('documents.index') }}">Centre documentaire</a></div>
            <div class="footer-contact"><h2>Contact & actualités</h2><a href="{{ route('contact') }}">Coordonnées du FOSER <span aria-hidden="true">↗</span></a><p>Recevez les actualités et les opportunités du Fonds.</p><form class="newsletter" method="POST" action="{{ route('newsletter.subscribe') }}">@csrf<label class="visually-hidden" for="footer-email">Votre adresse email</label><input id="footer-email" type="email" name="email" placeholder="Votre adresse email" autocomplete="email" required><button type="submit" aria-label="S'inscrire à la newsletter">→</button></form></div>
        </div>
        <div class="shell footer-bottom"><span>© {{ date('Y') }} FOSER · Tous droits réservés</span><a href="{{ route('institution.section', 'documents') }}">Documents officiels</a><span>Ouagadougou · Burkina Faso</span></div>
    </footer>
</body>
</html>
