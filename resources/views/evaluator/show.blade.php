<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Évaluation | FOSER</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="student-body">
<main class="shell student-main">
    <a href="{{ route('evaluator.dashboard') }}">← Candidatures attribuées</a>
    <p class="eyebrow">{{ $record->reference }} · {{ $evaluation->status }}</p>
    <h1>{{ $record->project_title ?: 'Candidature' }}</h1>
    @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="form-errors">{{ $errors->first() }}</div>@endif
    <section class="student-panel">
        <h2>Informations du projet</h2>
        <p><strong>Domaine :</strong> {{ $record->domain ?: 'Non renseigné' }}</p>
        <p><strong>Résumé :</strong> {{ $record->summary ?: 'Non renseigné' }}</p>
        <p><strong>Description :</strong> {{ $record->description ?: 'Non renseignée' }}</p>
        <p><strong>Objectifs :</strong> {{ $record->objectives ?: 'Non renseignés' }}</p>
        <p><strong>Méthodologie :</strong> {{ $record->methodology ?: 'Non renseignée' }}</p>
        <p><strong>Calendrier :</strong> {{ $record->calendar ?: 'Non renseigné' }}</p>
        <p><strong>Budget :</strong> {{ $record->budget ?? 'Non renseigné' }}</p>
    </section>
    <section class="student-panel">
        <h2>Pièces autorisées</h2>
        @forelse($documents as $document)
            <div class="application-item"><div><strong>{{ $document->title }}</strong><small>{{ $document->document_type }} · {{ $document->mime_type }} · {{ number_format($document->size / 1048576, 2) }} Mo</small></div><a href="{{ route('evaluator.applications.documents.download', [$record->id, $document->id]) }}">Télécharger</a></div>
        @empty
            <p>Aucune pièce jointe.</p>
        @endforelse
    </section>
    @if(in_array($evaluation->status, ['submitted', 'validated', 'conflict'], true))
        <div class="notice">Cette évaluation est verrouillée: {{ $evaluation->status }}.</div>
        <section class="student-panel"><h2>Grille soumise</h2>@foreach($criteria as $criterion)<p><strong>{{ $criterion->name }}:</strong> {{ $scores[$criterion->id] ?? 'Sans note' }} / {{ $criterion->maximum_score }}<br>{{ $criterionComments[$criterion->id] ?? '' }}</p>@endforeach<p><strong>Commentaire général:</strong> {{ $evaluation->comment ?: 'Aucun commentaire.' }}</p></section>
    @else
        <form method="POST" action="{{ route('evaluator.applications.evaluation', $record->id) }}" class="student-panel">
            @csrf
            <h2>Grille d’évaluation</h2>
            @forelse($criteria as $criterion)
                <label>{{ $criterion->name }} / {{ $criterion->maximum_score }}<input type="number" name="scores[{{ $criterion->id }}]" min="0" max="{{ $criterion->maximum_score }}" step="0.01" value="{{ old('scores.'.$criterion->id, $scores[$criterion->id] ?? '') }}" required><textarea name="comments[{{ $criterion->id }}]" maxlength="2000" placeholder="Commentaire pour ce critère">{{ old('comments.'.$criterion->id, $criterionComments[$criterion->id] ?? '') }}</textarea></label>
            @empty
                <p>Aucune grille n’est configurée pour ce programme. La soumission est indisponible.</p>
            @endforelse
            @if($criteria->isNotEmpty())<label>Commentaire général<textarea name="comment" maxlength="5000">{{ old('comment', $evaluation->comment) }}</textarea></label><button class="button button-lime" type="submit">Soumettre l’évaluation</button>@endif
        </form>
        <form method="POST" action="{{ route('evaluator.applications.conflict', $record->id) }}" class="student-panel">@csrf<label>Motif du conflit<textarea name="reason" maxlength="2000" required></textarea></label><button class="button button-dark" type="submit">Déclarer un conflit</button></form>
    @endif
</main>
</body>
</html>