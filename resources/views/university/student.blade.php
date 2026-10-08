@extends('university.layout')
@section('content')
<div class="student-welcome"><div><p class="eyebrow">FICHE ÉTUDIANT</p><h1>{{ $record->name }}</h1><p>{{ $record->inee }} · {{ $record->program ?: 'Filière non renseignée' }}</p></div></div>
<section class="student-grid"><article class="student-panel"><h2>Identité</h2><p>Email : {{ $record->email }}</p><p>Sexe : {{ $record->sex ?: 'Non renseigné' }}</p><p>Date de naissance : {{ $record->date_of_birth ?: 'Non renseignée' }}</p></article><article class="student-panel"><h2>Scolarité</h2><p>Filière : {{ $record->program ?: 'Non renseignée' }}</p><p>Niveau : {{ $record->study_level ?: 'Non renseigné' }}</p><p>Année : {{ $record->academic_year ?: 'Non renseignée' }}</p><p>Statut établissement : {{ $record->validation_status }}</p></article></section>
<section class="student-panel"><h2>Dossiers FOSER</h2>@forelse($applications as $application)<div class="application-item"><div><strong>{{ $application->reference }}</strong><small>{{ $application->program_name }}</small></div><span>{{ ucfirst($application->status) }}</span></div>@empty<p>Aucun dossier.</p>@endforelse</section>
@endsection
