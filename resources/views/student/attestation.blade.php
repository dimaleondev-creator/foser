<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attestation d’attribution | FOSER</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="student-body">
<main class="shell student-main">
    <div class="application-actions">
        <a class="button button-dark" href="{{ route('student.applications') }}">Retour aux dossiers</a>
        <button class="button button-lime" type="button" onclick="window.print()">Imprimer / enregistrer</button>
    </div>
    <article class="student-panel attestation-sheet">
        <p class="eyebrow">FONDS DE SOUTIEN À L’ÉDUCATION ET À LA RECHERCHE</p>
        <h1>Attestation d’attribution</h1>
        <p>Le Fonds de Soutien à l’Éducation et à la Recherche atteste que :</p>
        <h2>{{ $attestation->student_name }}</h2>
        <p>INEE : <strong>{{ $attestation->inee ?: 'Non renseigné' }}</strong></p>
        <p>Établissement : <strong>{{ $attestation->university_name ?: 'Non renseigné' }}</strong></p>
        <p>bénéficie d’une attribution au titre du programme <strong>{{ $attestation->program_name }}</strong>.</p>
        <dl>
            <div><dt>Référence du dossier</dt><dd>{{ $attestation->application_reference }}</dd></div>
            <div><dt>Référence de décision</dt><dd>{{ $attestation->decision_reference }}</dd></div>
            <div><dt>Montant attribué</dt><dd>{{ number_format((float) $attestation->amount, 0, ',', ' ') }} {{ $attestation->currency }}</dd></div>
            <div><dt>Date d’attribution</dt><dd>{{ \Carbon\Carbon::parse($attestation->award_date)->format('d/m/Y') }}</dd></div>
        </dl>
        <p class="attestation-note">Document généré depuis le dossier étudiant authentifié. Sa validité peut être vérifiée auprès du FOSER à partir des références ci-dessus.</p>
    </article>
</main>
</body>
</html>
