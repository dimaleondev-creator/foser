@extends('university.layout')
@section('content')
<div class="student-welcome">
    <div>
        <p class="eyebrow">REVUE DE CANDIDATURE</p>
        <h1>{{ $record->student_name }}</h1>
        <p>{{ $record->reference }} · {{ $record->program_name ?: 'Programme non renseigné' }} · {{ $record->status }}</p>
    </div>
</div>
<section class="student-grid">
    <article class="student-panel">
        <h2>Étudiant</h2>
        <p>{{ $record->student_email }} · INEE {{ $record->inee }}</p>
        <p>{{ $record->study_program ?: 'Formation non renseignée' }} · {{ $record->study_level ?: 'Niveau non renseigné' }} · {{ $record->academic_year ?: 'Année non renseignée' }}</p>
        <p>Vérification des informations par l’établissement : {{ $record->student_validation_status }}</p>
    </article>
    <article class="student-panel">
        <h2>Projet</h2>
        <p><strong>{{ $record->project_title ?: 'Titre non renseigné' }}</strong></p>
        <p>{{ $record->summary ?: 'Résumé non renseigné' }}</p>
        <p>{{ $record->description ?: 'Description non renseignée' }}</p>
        <p>Domaine : {{ $record->domain ?: 'Non renseigné' }} · Budget : {{ $record->budget ?? 'Non renseigné' }}</p>
        <p>Objectifs : {{ $record->objectives ?: 'Non renseignés' }}</p>
        <p>Méthodologie : {{ $record->methodology ?: 'Non renseignée' }}</p>
        <p>Calendrier : {{ $record->calendar ?: 'Non renseigné' }}</p>
    </article>
</section>
<section class="student-panel">
    <h2>Pièces justificatives</h2>
    @forelse($documents as $document)
        <div class="application-item">
            <div><strong>{{ $document->title }}</strong><small>{{ $document->document_type }} · {{ number_format($document->size / 1048576, 2) }} Mo · {{ $document->mime_type }}</small></div>
            <span>{{ ucfirst($document->review_status) }} @if($document->comment) · {{ $document->comment }} @endif</span>
            <a href="{{ route('university.applications.documents.download', [$record->id, $document->id]) }}">Télécharger</a>
        </div>
    @empty
        <p>Aucune pièce déposée.</p>
    @endforelse
</section>
<section class="student-panel">
    <h2>Actions de revue</h2>
    @if(in_array($record->status, ['soumis', 'verification', 'incomplet', 'complement'], true))
        <form method="POST" action="{{ route('university.applications.validate', $record->id) }}" class="application-actions">@csrf<button class="button button-lime" type="submit">Valider la candidature</button></form>
        <div class="student-grid">
            <form method="POST" action="{{ route('university.applications.correction', $record->id) }}" class="form-panel">@csrf<label>Motif de correction<textarea name="reason" maxlength="2000" required></textarea></label><button class="button button-dark" type="submit">Demander une correction</button></form>
            <form method="POST" action="{{ route('university.applications.reject', $record->id) }}" class="form-panel">@csrf<label>Motif du rejet<textarea name="reason" maxlength="2000" required></textarea></label><button class="button button-dark" type="submit">Rejeter la candidature</button></form>
        </div>
    @else
        <p>Aucune action de revue n’est disponible pour ce statut.</p>
    @endif
</section>
<section class="student-panel">
    <h2>Historique des validations</h2>
    @forelse($history as $event)
        <div class="application-item"><div><strong>{{ ucfirst($event->to_status) }}</strong><small>{{ \Carbon\Carbon::parse($event->changed_at)->format('d/m/Y à H:i') }}</small></div><span>{{ $event->reason ?: 'Aucun motif' }}</span></div>
    @empty
        <p>Aucun changement enregistré.</p>
    @endforelse
</section>
@endsection
