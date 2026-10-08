<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mon profil | FOSER</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="student-body">
    <header class="student-header">
        <div class="shell nav-wrap">
            <a class="brand" href="{{ url('/') }}"><x-site-logo-mark /><span><strong>FOSER</strong><small>Espace étudiant</small></span></a>
            <nav class="student-nav" aria-label="Navigation étudiant">
                <a href="{{ route('student.dashboard') }}">Tableau de bord</a>
                <a href="{{ route('student.applications') }}">Mes dossiers</a>
                <a class="active" href="{{ route('student.profile') }}" aria-current="page">Mon profil</a>
            </nav>
            <form method="POST" action="{{ route('auth.logout') }}">@csrf<button class="button button-dark" type="submit">Déconnexion</button></form>
        </div>
    </header>

    <main class="shell student-main">
        <div class="student-welcome">
            <div>
                <p class="eyebrow">MON PROFIL</p>
                <h1>Vos informations.</h1>
                <p>Profil complété : <strong>{{ $completion }} %</strong></p>
                <p>{{ $university?->name ?: 'Université non renseignée' }} · {{ $profile?->program ?: 'Filière non renseignée' }}</p>
            </div>
        </div>
        @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="form-errors" role="alert">{{ $errors->first() }}</div>@endif

        <section class="student-panel form-panel">
            <form method="POST" action="{{ route('student.profile.update') }}">
                @csrf
                @method('PUT')

                <div class="form-row">
                    <label>Nom complet<input value="{{ auth()->user()->name }}" disabled></label>
                    <label>Email<input value="{{ auth()->user()->email }}" disabled></label>
                </div>

                <h2>Coordonnées</h2>
                <div class="form-row">
                    <label>Numéro de téléphone pour le paiement
                        <input name="phone" type="tel" inputmode="tel" value="{{ old('phone', $profile?->phone) }}" maxlength="40" required autocomplete="tel">
                    </label>
                    <label>Autre numéro de téléphone
                        <input name="other_phone" type="tel" inputmode="tel" value="{{ old('other_phone', $profile?->other_phone) }}" maxlength="40" autocomplete="tel">
                    </label>
                </div>
                <div class="form-row">
                    <label>Adresse<input name="address" value="{{ old('address', $profile?->address) }}" maxlength="255"></label>
                    <label>Date de naissance<input name="date_of_birth" type="date" value="{{ old('date_of_birth', $profile?->date_of_birth) }}"></label>
                </div>
                <div class="form-row">
                    <label>Lieu de naissance<input name="birth_place" value="{{ old('birth_place', $profile?->birth_place) }}" maxlength="120"></label>
                    <label>Nationalité<input name="nationality" value="{{ old('nationality', $profile?->nationality) }}" maxlength="100"></label>
                </div>

                <h2>Pièces d’identité</h2>
                <div class="form-row">
                    <label>Numéro CNIB
                        <input name="national_id" type="text" value="{{ old('national_id', $profile?->national_id) }}" maxlength="20" autocomplete="off" spellcheck="false" aria-describedby="national-id-help">
                        <small id="national-id-help">20 caractères maximum. Les lettres et les zéros initiaux sont conservés.</small>
                    </label>
                    <label>Numéro NIP
                        <input name="nip" type="text" value="{{ old('nip', $profile?->nip) }}" maxlength="20" autocomplete="off" spellcheck="false" aria-describedby="nip-help">
                        <small id="nip-help">20 caractères maximum. Les lettres et les zéros initiaux sont conservés.</small>
                    </label>
                </div>
                <div class="form-row">
                    <label>Sexe
                        <select name="sex">
                            <option value="">Non renseigné</option>
                            <option value="female" @selected(old('sex', $profile?->sex) === 'female')>Femme</option>
                            <option value="male" @selected(old('sex', $profile?->sex) === 'male')>Homme</option>
                            <option value="other" @selected(old('sex', $profile?->sex) === 'other')>Autre</option>
                        </select>
                    </label>
                    <label>Région<input name="region" value="{{ old('region', $profile?->region) }}" maxlength="100"></label>
                </div>
                <label>Province / ville<input name="province" value="{{ old('province', $profile?->province) }}" maxlength="100"></label>

                <h2>Scolarité</h2>
                <div class="form-row">
                    <label>Université / établissement<input value="{{ $university?->name ?? 'Non renseigné' }}" readonly aria-readonly="true"></label>
                    <label>Faculté / établissement<input name="faculty" value="{{ old('faculty', $profile?->faculty) }}" maxlength="160"></label>
                </div>
                <div class="form-row">
                    <label>Filière<input name="program" value="{{ old('program', $profile?->program) }}" maxlength="255"></label>
                    <label>Niveau<input name="study_level" value="{{ old('study_level', $profile?->study_level) }}" maxlength="80"></label>
                </div>
                <label>Année académique<input name="academic_year" value="{{ old('academic_year', $profile?->academic_year) }}" maxlength="20"></label>

                <h2>Informations du père</h2>
                <div class="form-row">
                    <label>Prénom du père<input name="father_first_name" value="{{ old('father_first_name', $profile?->father_first_name) }}" maxlength="120"></label>
                    <label>Nom du père<input name="father_last_name" value="{{ old('father_last_name', $profile?->father_last_name) }}" maxlength="120"></label>
                </div>
                <div class="form-row">
                    <label>Pays de résidence du père<input name="father_residence_country" value="{{ old('father_residence_country', $profile?->father_residence_country) }}" maxlength="100"></label>
                    <label>Fonction du père<input name="father_function" value="{{ old('father_function', $profile?->father_function) }}" maxlength="160"></label>
                </div>

                <h2>Informations de la mère</h2>
                <div class="form-row">
                    <label>Prénom de la mère<input name="mother_first_name" value="{{ old('mother_first_name', $profile?->mother_first_name) }}" maxlength="120"></label>
                    <label>Nom de la mère<input name="mother_last_name" value="{{ old('mother_last_name', $profile?->mother_last_name) }}" maxlength="120"></label>
                </div>
                <div class="form-row">
                    <label>Pays de résidence de la mère<input name="mother_residence_country" value="{{ old('mother_residence_country', $profile?->mother_residence_country) }}" maxlength="100"></label>
                    <label>Fonction de la mère<input name="mother_function" value="{{ old('mother_function', $profile?->mother_function) }}" maxlength="160"></label>
                </div>

                <h2>Personne à contacter en urgence</h2>
                <div class="form-row">
                    <label>Nom<input name="emergency_contact_name" value="{{ old('emergency_contact_name', $profile?->emergency_contact_name) }}" maxlength="160"></label>
                    <label>Téléphone<input name="emergency_contact_phone" type="tel" inputmode="tel" value="{{ old('emergency_contact_phone', $profile?->emergency_contact_phone) }}" maxlength="40"></label>
                </div>

                <button class="button button-dark" type="submit">Enregistrer les modifications <span aria-hidden="true">→</span></button>
            </form>
        </section>
    </main>
</body>
</html>