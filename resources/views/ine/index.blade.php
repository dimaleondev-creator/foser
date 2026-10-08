<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Gestion de mon INE | FOSER</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="ine-body">
<header class="ine-header">
    <a class="brand" href="{{ route('home') }}" aria-label="FOSER, accueil">
        <x-site-logo-mark />
        <span><strong>FOSER</strong><small>ESPACE ÉTUDIANT</small></span>
    </a>
    <nav aria-label="Navigation principale">
        @auth
            <a href="{{ route('student.dashboard') }}">Mon espace</a>
        @else
            <a href="{{ route('student.login') }}">Connexion</a>
        @endauth
    </nav>
</header>

<main class="ine-main">
    <section class="ine-intro">
        <p class="ine-eyebrow">IDENTITÉ ÉTUDIANTE</p>
        <h1>Gestion de mon INE</h1>
        <p>Accédez aux services FOSER grâce à votre Identifiant National Étudiant.</p>
    </section>

    @if (session('status'))
        <div class="ine-notice" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="ine-error" role="alert"><strong>Vérifiez les informations saisies.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    @if ($mode === 'home')
        @if ($profile?->inee)
            <section class="ine-identity" aria-labelledby="ine-identity-title">
                <div><p class="ine-eyebrow">MON IDENTITÉ</p><h2 id="ine-identity-title">{{ $profile->first_name ?: auth()->user()->name }} {{ $profile->last_name }}</h2></div>
                <div class="ine-identity-value"><span>INE</span><strong>{{ $profile->inee }}</strong></div>
                <p class="ine-status"><span aria-hidden="true">{{ $profile->ine_status === 'verified' ? '✓' : '•' }}</span> Statut : {{ match($profile->ine_status) { 'verified' => 'Vérifié', 'rejected' => 'Rejeté', 'suspended' => 'Suspendu', default => 'En attente de vérification' } }}</p>
            </section>
        @endif
        <section class="ine-choice-grid" aria-label="Services INE">
            <article class="ine-choice">
                <span class="ine-choice-index">01</span>
                <div><h2>Je connais mon INE</h2><p>J’ai déjà mon INE et je souhaite l’associer à mon compte étudiant.</p></div>
                <a class="ine-button" href="{{ route('ine.declare') }}">Déclarer mon INE <span aria-hidden="true">→</span></a>
            </article>
            <article class="ine-choice">
                <span class="ine-choice-index">02</span>
                <div><h2>Je ne connais pas mon INE</h2><p>Je souhaite retrouver mon INE à partir de mes informations personnelles.</p></div>
                <a class="ine-button ine-button-secondary" href="{{ route('ine.recover') }}">Rechercher mon INE <span aria-hidden="true">→</span></a>
            </article>
        </section>
        <p class="ine-footnote">Les INE déclarés sont associés au compte puis vérifiés par les services du FOSER. La récupération ne révèle aucun identifiant avant confirmation par code temporaire.</p>
    @elseif ($mode === 'declare')
        <section class="ine-form-panel">
            <a class="ine-back" href="{{ route('ine.index') }}">← Tous les services INE</a>
            <p class="ine-eyebrow">DÉCLARATION · ÉTAPE 1 SUR 2</p>
            <h2>Associer mon INE</h2>
            @if ($profile?->ine_status === 'verified')
                <div class="ine-success" role="status"><strong>INE déjà vérifié</strong><p>Votre identifiant est associé à ce compte et vérifié par le FOSER.</p><p class="ine-code">{{ $profile->inee }}</p></div>
            @elseif ($pendingDeclaration)
                <div class="ine-success" role="status"><strong>Vérification préalable effectuée</strong><p>Cette étape ne consulte pas de registre national. L’association sera vérifiée par le FOSER après connexion à votre compte étudiant.</p><p class="ine-code">{{ $pendingDeclaration['inee'] }}</p>
                    @if (auth()->user()?->account_type === 'etudiant')
                        <p>Le nom fourni correspond au compte connecté. Confirmez pour associer cet INE à votre profil.</p>
                        <form method="POST" action="{{ route('ine.confirm') }}">@csrf<button class="ine-button" type="submit">Confirmer mon INE <span aria-hidden="true">→</span></button></form>
                    @else
                        <p>Connectez-vous ou créez votre compte étudiant avec les mêmes nom et prénom pour terminer l’association.</p>
                        <a class="ine-button" href="{{ route('student.login') }}">Se connecter <span aria-hidden="true">→</span></a>
                        <a class="ine-back" href="{{ route('student.register') }}">Créer un compte étudiant</a>
                    @endif
                </div>
            @else
                <p class="ine-form-lead">Saisissez les informations demandées. Vous pourrez vérifier votre compte étudiant avant de confirmer l’association; le FOSER vérifiera ensuite votre déclaration.</p>
                <form class="ine-form" method="POST" action="{{ route('ine.verify') }}">
                    @csrf
                    <label for="last_name">Nom <span aria-hidden="true">*</span></label>
                    <input id="last_name" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name" maxlength="120" required aria-describedby="last_name-error">
                    @error('last_name')<span class="ine-field-error" id="last_name-error">{{ $message }}</span>@enderror
                    <label for="first_name">Prénom(s) <span aria-hidden="true">*</span></label>
                    <input id="first_name" name="first_name" value="{{ old('first_name') }}" autocomplete="given-name" maxlength="120" required>
                    <label for="inee">INE <span aria-hidden="true">*</span></label>
                    <input id="inee" name="inee" value="{{ old('inee') }}" placeholder="Exemple : N04990420252" autocomplete="off" autocapitalize="characters" maxlength="80" pattern="[A-Za-z0-9-]+" required>
                    <button class="ine-button" type="submit">Vérifier mon INE <span aria-hidden="true">→</span></button>
                </form>
            @endif
        </section>
    @else
        <section class="ine-form-panel">
            <a class="ine-back" href="{{ route('ine.index') }}">← Tous les services INE</a>
            <p class="ine-eyebrow">RÉCUPÉRATION SÉCURISÉE</p>
            <h2>Rechercher mon INE</h2>
            @if ($recoveredIne)
                <div class="ine-success" role="status"><strong>Identité vérifiée</strong><p>Votre INE</p><p class="ine-code" id="recovered-ine">{{ $recoveredIne }}</p><button class="ine-copy" type="button" data-copy-ine="recovered-ine">Copier mon INE</button><a class="ine-button" href="{{ route('student.login') }}">Continuer <span aria-hidden="true">→</span></a></div>
            @elseif ($otpSent)
                <p class="ine-form-lead" role="status">Si une correspondance existe, un code temporaire a été envoyé à l’adresse associée au compte.</p>
                <form class="ine-form" method="POST" action="{{ route('ine.otp.verify') }}">
                    @csrf
                    <label for="code">Code à 6 chiffres</label>
                    <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
                    <p class="ine-helper">Le code expire après 10 minutes et devient inutilisable après 5 tentatives.</p>
                    <button class="ine-button" type="submit">Vérifier le code <span aria-hidden="true">→</span></button>
                </form>
            @elseif ($recoverySearched)
                <div class="ine-neutral" role="status"><strong>Vérification supplémentaire</strong><p>Si une correspondance existe, les instructions seront envoyées à l’adresse déjà associée au compte. Aucune adresse ni donnée personnelle ne sera révélée ici.</p></div>
                <form method="POST" action="{{ route('ine.otp.send') }}">@csrf<button class="ine-button" type="submit">Recevoir mon code <span aria-hidden="true">→</span></button></form>
            @else
                <p class="ine-form-lead">Saisissez les informations correspondant à votre compte. Une vérification supplémentaire sera nécessaire avant tout affichage de l’INE.</p>
                <form class="ine-form" method="POST" action="{{ route('ine.search') }}">
                    @csrf
                    <label for="search_last_name">Nom <span aria-hidden="true">*</span></label>
                    <input id="search_last_name" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name" maxlength="120" required>
                    <label for="search_first_name">Prénom(s) <span aria-hidden="true">*</span></label>
                    <input id="search_first_name" name="first_name" value="{{ old('first_name') }}" autocomplete="given-name" maxlength="120" required>
                    <label for="date_of_birth">Date de naissance <span aria-hidden="true">*</span></label>
                    <input id="date_of_birth" type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" autocomplete="bday" max="{{ now()->subDay()->format('Y-m-d') }}" required>
                    <label for="birth_place">Lieu de naissance <span aria-hidden="true">*</span></label>
                    <input id="birth_place" name="birth_place" value="{{ old('birth_place') }}" autocomplete="off" maxlength="120" required>
                    <button class="ine-button" type="submit">Rechercher mon INE <span aria-hidden="true">→</span></button>
                </form>
            @endif
        </section>
    @endif
</main>
<script>
document.querySelector('[data-copy-ine]')?.addEventListener('click', async (event) => {
    const value = document.getElementById(event.currentTarget.dataset.copyIne)?.textContent?.trim();
    if (value) await navigator.clipboard.writeText(value);
    event.currentTarget.textContent = 'INE copié';
});
</script>
</body>
</html>