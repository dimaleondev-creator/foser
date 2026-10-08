<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $mode === 'register' ? 'Créer votre compte étudiant' : 'Connexion' }} | FOSER</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body>
<main class="auth-shell">
    <a class="brand" href="{{ url('/') }}"><x-site-logo-mark /><span><strong>FOSER</strong><small>Fonds de Soutien à l'Éducation<br>et à la Recherche</small></span></a>
    <section class="auth-card">
        <div class="section-kicker"><span>ESPACE ÉTUDIANT</span><span>Accès sécurisé</span></div>
        <h1>{{ $mode === 'register' ? 'Créer votre compte étudiant.' : 'Bon retour.' }}</h1>
        <p class="lead">{{ $mode === 'register' ? 'Commencez votre parcours auprès du FOSER.' : 'Accédez à vos dossiers et opportunités.' }}</p>
        <form method="POST" action="{{ $mode === 'register' ? route('student.register.store') : route('student.login.store') }}">
            @csrf
            @if ($mode === 'register')
                <label>Nom complet<input name="name" value="{{ old('name') }}" required></label>
                <label>Téléphone<input name="phone" value="{{ old('phone') }}" required></label>
                <label>Pays ou lieu de naissance<input name="birth_place" value="{{ old('birth_place') }}" maxlength="120" required></label>
                <label>Date de naissance<input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}"></label>
                <label>Sexe<input name="sex" value="{{ old('sex') }}"></label>
                <label>Région<input name="region" value="{{ old('region') }}"></label>
                <label><input type="checkbox" name="terms" value="1" required> J'accepte les conditions d'utilisation</label>
            @endif
            <label>{{ $mode === 'register' ? 'Email' : 'Email ou INE' }}<input type="{{ $mode === 'register' ? 'email' : 'text' }}" name="{{ $mode === 'register' ? 'email' : 'login' }}" value="{{ old($mode === 'register' ? 'email' : 'login') }}" placeholder="{{ $mode === 'register' ? '' : 'Votre email ou votre INE' }}" required></label>
            <label>{{ $mode === 'login' ? 'Mot de passe ou code INE à 10 chiffres' : 'Mot de passe' }}<input type="password" name="password" autocomplete="current-password" required></label>
            @if ($mode === 'register')
                <label>Confirmer le mot de passe<input type="password" name="password_confirmation" required></label>
            @endif
            <button class="button button-dark" type="submit">{{ $mode === 'register' ? 'Créer votre compte étudiant' : 'Se connecter' }} <span>→</span></button>
        </form>
        @if ($errors->any())<div class="form-errors">{{ $errors->first() }}</div>@endif
        <p class="auth-switch">{{ $mode === 'register' ? 'Vous avez déjà un compte ?' : 'Pas encore de compte ?' }} <a href="{{ $mode === 'register' ? route('student.login') : route('student.register') }}">{{ $mode === 'register' ? 'Se connecter' : "S'inscrire" }}</a></p>
        <p class="auth-switch"><a href="{{ route('researcher.register') }}">Créer un compte chercheur</a></p>
        @if ($mode === 'register')<p class="auth-switch">Vous avez déjà votre INE ? <a href="{{ route('ine.index') }}">Accéder à sa gestion</a></p>@else<p class="auth-switch">Avec votre INE, saisissez le code de connexion à 10 chiffres reçu par email. <a href="{{ route('ine.recover') }}">Retrouver mon INE</a></p>@endif
        <a class="button button-dark" href="{{ route('home') }}">Retour à l'accueil</a>
    </section>
</main>
</body>
</html>
