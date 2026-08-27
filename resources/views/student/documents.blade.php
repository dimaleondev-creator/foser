<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Documents | FOSER</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="student-body">
<header class="student-header"><div class="shell nav-wrap"><a class="brand" href="{{ url('/') }}"><span class="brand-mark">F</span><span><strong>FOSER</strong><small>Espace étudiant</small></span></a><nav class="student-nav"><a href="{{ route('student.dashboard') }}">Tableau de bord</a><a href="{{ route('student.applications') }}">Mes dossiers</a><a href="{{ route('student.profile') }}">Mon profil</a><a class="active" href="{{ route('student.documents') }}">Documents</a><a href="{{ route('student.support') }}">Aide</a></nav><form method="POST" action="{{ route('auth.logout') }}">@csrf<button class="button button-dark" type="submit">Déconnexion</button></form></div></header>
<main class="shell student-main">
    <div class="student-welcome"><div><p class="eyebrow">MES DOCUMENTS</p><h1>Vos pièces justificatives.</h1><p>Les documents ne peuvent être modifiés que pendant un brouillon ou une demande de complément.</p></div></div>
    @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="form-errors">{{ $errors->first() }}</div>@endif
    <section class="student-panel form-panel"><h2>Téléverser une pièce</h2><form method="POST" action="{{ route('student.documents.upload') }}" enctype="multipart/form-data"><label>Dossier<input name="application_id" value="{{ old('application_id', request('application_id')) }}" required placeholder="Identifiant UUID du dossier"></label><label>Type de document<input name="document_type" value="{{ old('document_type') }}" required placeholder="Ex. certificat d'inscription"></label><label>Fichier<input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required></label><button class="button button-dark" type="submit">Envoyer la pièce <span>→</span></button></form></section>
    <section class="student-panel"><div class="panel-title"><h2>Pièces déposées</h2></div>@forelse($documents as $document)<div class="application-item"><small>{{ $document->document_type }}</small><strong>{{ $document->title }}</strong><span>{{ ucfirst($document->document_status) }}</span><a href="{{ route('student.documents.download', $document->id) }}">Télécharger</a></div>@empty<div class="student-empty">Aucune pièce déposée.</div>@endforelse</section>
</main>
</body>
</html>
