<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ app()->getLocale() === 'en' ? 'Organization' : 'Organigramme' }} | FOSER</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body>
    <div class="topline"><div class="shell top-inner"><span>RÉPUBLIQUE DU BURKINA FASO</span><a href="{{ url('/contact') }}">Nous contacter</a></div></div>
    <header class="site-header"><div class="shell nav-wrap"><a class="brand" href="{{ url('/') }}"><span class="brand-mark">F</span><span><strong>FOSER</strong><small>Fonds de Soutien à l'Éducation<br>et à la Recherche</small></span></a><nav class="main-nav"><a href="{{ url('/') }}">Accueil</a><a href="{{ url('/about') }}">Le FOSER</a><a href="{{ url('/programs') }}">Nos programmes</a><a href="{{ url('/calls') }}">Appels à candidatures</a><a class="active" href="{{ route('organization') }}">Organisation</a></nav><a class="button button-dark" href="{{ route('student.login') }}">Espace étudiant <span>↗</span></a></div></header>
    <main class="organization-page">
        <header class="page-hero"><div class="shell"><p class="section-kicker">FOSER</p><h1>{{ app()->getLocale() === 'en' ? 'Our organization' : 'Notre organisation' }}</h1></div></header>
        <section class="organization-shell shell" data-organization-tree>
            @forelse ($organization as $unit)
                @include('public.partials.organization-node', ['unit' => $unit])
            @empty
                <p class="empty-state">{{ app()->getLocale() === 'en' ? 'The organization chart is being prepared.' : 'L’organigramme est en cours de préparation.' }}</p>
            @endforelse
        </section>
    </main>
    <footer class="footer"><div class="shell footer-main"><div class="footer-brand"><a class="brand brand-light" href="{{ url('/') }}"><span class="brand-mark">F</span><span><strong>FOSER</strong><small>Fonds de Soutien à l'Éducation<br>et à la Recherche</small></span></a><p>Construire les capacités.<br>Faire grandir les possibles.</p></div></div></footer>
</body>
</html>
