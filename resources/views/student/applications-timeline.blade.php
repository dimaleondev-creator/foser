<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mes dossiers | FOSER</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'))) @vite(['resources/css/app.css', 'resources/js/app.js']) @endif
</head>
<body class="student-body">
<header class="student-header"><div class="shell nav-wrap"><a class="brand" href="{{ url('/') }}"><span class="brand-mark">F</span><span><strong>FOSER</strong><small>Espace étudiant</small></span></a><nav class="student-nav"><a href="{{ route('student.dashboard') }}">Tableau de bord</a><a class="active" href="{{ route('student.applications') }}">Mes dossiers</a><a href="{{ route('student.profile') }}">Mon profil</a></nav><form method="POST" action="{{ route('auth.logout') }}">@csrf<button class="button button-dark" type="submit">Déconnexion</button></form></div></header>
<main class="shell student-main">
<div class="student-welcome"><div><p class="eyebrow">MES DOSSIERS</p><h1>Vos candidatures.</h1><p>Suivez chaque étape de traitement de vos dossiers.</p></div><form method="POST" action="{{ route('student.applications.create') }}">@csrf<button class="button button-lime" type="submit">Nouveau dossier <span>+</span></button></form></div>
@if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
@if($errors->any())<div class="form-errors">{{ $errors->first() }}</div>@endif
@forelse($applications as $application)
<section class="student-panel application-card">
<div class="panel-title"><div><small>{{ $application->reference }}</small><h2>{{ $application->program_name ?: 'Programme non renseigné' }}</h2></div><strong>{{ \App\Enums\StudentApplicationStatus::tryFrom($application->status)?->label() ?? ucfirst($application->status) }}</strong></div>
<p>Dépôt : {{ $application->submitted_at ? \Carbon\Carbon::parse($application->submitted_at)->format('d/m/Y à H:i') : 'Pas encore soumis' }} · Dernière action : {{ \Carbon\Carbon::parse($application->updated_at)->format('d/m/Y à H:i') }}</p>
@if($application->responsible_agent)<p>Agent responsable : {{ $application->responsible_agent }}</p>@endif
<div class="progress"><span style="width: {{ $application->progress }}%"></span></div>
<p>Progression : {{ $application->progress }} %</p>
@if(count($application->missing_documents))<div class="form-errors"><strong>Pièces manquantes :</strong> {{ implode(', ', $application->missing_documents) }}</div>@endif
<div class="application-actions">@if($application->status === 'brouillon' || $application->status === 'complement')<a class="button button-dark" href="{{ route('student.applications.edit', $application->id) }}">Modifier le dossier</a><a class="button button-dark" href="{{ route('student.documents', ['application_id' => $application->id]) }}">Compléter les pièces</a>@endif @if($application->status === 'brouillon')<form method="POST" action="{{ route('student.applications.submit', $application->id) }}">@csrf<button class="button button-lime" type="submit">Soumettre</button></form>@endif</div>
<h3>Historique</h3><ol>@foreach($application->history as $event)<li><strong>{{ \App\Enums\StudentApplicationStatus::tryFrom($event->to_status)?->label() ?? ucfirst($event->to_status) }}</strong> · {{ \Carbon\Carbon::parse($event->changed_at)->format('d/m/Y à H:i') }} @if($event->reason)<span>{{ $event->reason }}</span>@endif</li>@endforeach</ol>
</section>
@empty<section class="student-panel"><div class="student-empty">Aucun dossier pour le moment.</div></section>@endforelse
</main></body></html>
