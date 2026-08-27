<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $mode === 'register' ? 'Inscription chercheur' : 'Connexion chercheur' }} | FOSER</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="auth-shell">
        <a class="brand" href="{{ route('home') }}">
            <span class="brand-mark">F</span>
            <span>
                <strong>FOSER</strong>
                <small>Espace chercheur</small>
            </span>
        </a>

        <section class="auth-card">
            <p class="eyebrow">ESPACE CHERCHEUR</p>
            <h1>{{ $mode === 'register' ? 'Créer votre compte chercheur' : 'Connexion chercheur' }}</h1>

            <form method="POST" action="{{ $mode === 'register' ? route('researcher.register.store') : route('researcher.login.store') }}">
                @csrf

                @if($mode === 'register')
                    <label>Prénom<input name="first_name" value="{{ old('first_name') }}" required></label>
                    <label>Nom<input name="last_name" value="{{ old('last_name') }}" required></label>
                    <label>Téléphone<input name="phone" value="{{ old('phone') }}" required></label>
                    <label>Pays<input name="country" value="{{ old('country') }}" required></label>
                    <label>Région<input name="region" value="{{ old('region') }}"></label>
                    <label>Ville<input name="city" value="{{ old('city') }}"></label>
                    <label>Domaine de recherche<input name="research_domain" value="{{ old('research_domain') }}" required></label>
                    <label>Spécialité<input name="speciality" value="{{ old('speciality') }}" required></label>
                    <label>Grade académique<input name="academic_rank" value="{{ old('academic_rank') }}"></label>
                    <label>
                        Institution
                        <select name="university_id">
                            <option value="">Sélectionner</option>
                            @foreach($universities as $university)
                                <option value="{{ $university->id }}">{{ $university->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Laboratoire
                        <select name="laboratory_id">
                            <option value="">Sélectionner</option>
                            @foreach($laboratories as $laboratory)
                                <option value="{{ $laboratory->id }}">{{ $laboratory->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Fonction<input name="position" value="{{ old('position') }}"></label>
                    <label>ORCID<input name="orcid" value="{{ old('orcid') }}"></label>
                    <label>Années d'expérience<input type="number" name="years_experience" min="0" max="80" value="{{ old('years_experience') }}"></label>
                    <label>Biographie<textarea name="biography">{{ old('biography') }}</textarea></label>
                    <label>Publications principales<textarea name="main_publications">{{ old('main_publications') }}</textarea></label>
                    <label><input type="checkbox" name="terms" value="1" required> J'accepte les conditions d'utilisation et la politique de confidentialité</label>
                @endif

                <label>Email<input type="email" name="email" value="{{ old('email') }}" required></label>
                <label>Mot de passe<input type="password" name="password" required></label>

                @if($mode === 'register')
                    <label>Confirmation du mot de passe<input type="password" name="password_confirmation" required></label>
                @endif

                <button class="button button-dark" type="submit">{{ $mode === 'register' ? 'Envoyer ma demande' : 'Se connecter' }}</button>
            </form>

            @if($errors->any())
                <div class="form-errors">{{ $errors->first() }}</div>
            @endif

            @if($mode === 'register')
                <p><a href="{{ route('researcher.login') }}">Déjà inscrit ? Se connecter</a></p>
            @else
                <p><a href="{{ route('researcher.register') }}">Créer un compte chercheur</a></p>
            @endif
        </section>
    </main>
</body>
</html>
