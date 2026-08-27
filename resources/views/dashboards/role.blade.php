<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ucfirst(str_replace('_', ' ', $role)) }} | FOSER</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'))) @vite(['resources/css/app.css', 'resources/js/app.js']) @endif
</head>
<body class="student-body">
    <header class="student-header">
        <div class="shell nav-wrap">
            <a class="brand" href="{{ url('/') }}"><span class="brand-mark">F</span><span><strong>FOSER</strong><small>Espace institutionnel</small></span></a>
            <form method="POST" action="{{ route('auth.logout') }}">@csrf<button class="button button-dark" type="submit">Déconnexion</button></form>
        </div>
    </header>
    <main class="shell student-main">
        <div class="student-welcome">
            <div><p class="eyebrow">ESPACE {{ strtoupper(str_replace('_', ' ', $role)) }}</p><h1>Bonjour, {{ $user->name }}.</h1><p>Retrouvez les indicateurs et les actions autorisés pour votre fonction.</p></div>
        </div>
        <nav class="student-nav" aria-label="Navigation principale">
            <a class="active" href="{{ request()->url() }}">Tableau de bord</a>
            <a href="{{ route('notifications.history') }}">Notifications</a>
        </nav>
        <section class="student-grid">
            @foreach ($stats as $label => $value)
                <article class="student-panel"><div class="stats-grid"><div><strong>{{ $value }}</strong><span>{{ $label }}</span></div></div></article>
            @endforeach
        </section>
        <section class="student-panel"><div class="panel-title"><h2>Accès fonctionnels</h2></div><p>Les modules accessibles sont déterminés par vos permissions serveur.</p></section>
    </main>
</body>
</html>
