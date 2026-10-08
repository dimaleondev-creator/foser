@extends('researcher.layout')
@section('content')
<div class="student-welcome">
    <div><p class="eyebrow">{{ $project->reference }} · {{ ucfirst(str_replace('_', ' ', $project->status)) }}</p><h1>{{ $project->title }}</h1><p>{{ $project->abstract ?: 'Résumé scientifique à compléter.' }}</p></div>
    @if($project->status === 'draft')
        <form method="POST" action="{{ route('researcher.projects.submit', $project->id) }}">@csrf<button class="button button-lime" type="submit">Soumettre la candidature</button></form>
    @endif
</div>

@if($errors->any())
    <div class="form-errors" role="alert">
        <strong>Complétez les champs requis avant la soumission :</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="student-grid">
    <section class="student-panel">
        <h2>Informations du projet</h2>
        <dl class="research-project-details">
            <div><dt>Domaine de recherche</dt><dd>{{ $project->domain ?: 'À renseigner' }}</dd></div>
            <div><dt>Budget demandé</dt><dd>{{ $project->budget !== null ? number_format($project->budget, 0, ',', ' ').' '.($project->currency ?: 'FCFA') : 'À renseigner' }}</dd></div>
            <div><dt>Début prévu</dt><dd>{{ $project->starts_at ? \Carbon\Carbon::parse($project->starts_at)->format('d/m/Y') : 'Non renseigné' }}</dd></div>
            <div><dt>Fin prévue</dt><dd>{{ $project->ends_at ? \Carbon\Carbon::parse($project->ends_at)->format('d/m/Y') : 'Non renseignée' }}</dd></div>
        </dl>
        @if(in_array($project->status, ['draft', 'complement'], true) && $project->principal_researcher_id === auth()->id())
            <form method="POST" action="{{ route('researcher.projects.update', $project->id) }}" class="stack research-project-edit">
                @csrf
                @method('PUT')
                <h2>Compléter ou modifier le projet</h2>
                <label>Titre du projet<input name="title" value="{{ old('title', $project->title) }}" maxlength="255" required></label>
                <label>Résumé scientifique<textarea name="abstract" maxlength="10000" required>{{ old('abstract', $project->abstract) }}</textarea></label>
                <label>Domaine de recherche<input name="domain" value="{{ old('domain', $project->domain) }}" maxlength="255" required></label>
                <label>Laboratoire<select name="laboratory_id"><option value="">Aucun laboratoire</option>@foreach(DB::table('laboratories')->where('status', 'active')->orderBy('name')->get() as $laboratory)<option value="{{ $laboratory->id }}" @selected(old('laboratory_id', $project->laboratory_id) === $laboratory->id)>{{ $laboratory->name }}</option>@endforeach</select></label>
                <div class="form-row"><label>Budget demandé<input name="budget" type="number" min="0" step="0.01" value="{{ old('budget', $project->budget) }}" required></label><label>Devise<input name="currency" value="{{ old('currency', $project->currency ?: 'FCFA') }}" maxlength="3"></label></div>
                <div class="form-row"><label>Date de début<input name="starts_at" type="date" value="{{ old('starts_at', $project->starts_at) }}"></label><label>Date de fin<input name="ends_at" type="date" value="{{ old('ends_at', $project->ends_at) }}"></label></div>
                <button class="button button-dark" type="submit">Enregistrer les modifications</button>
            </form>
        @endif
    </section>

    <section class="student-panel">
        <h2>Membres</h2>
        @forelse($members as $member)<p><strong>{{ $member->name }}</strong> · {{ $member->role }}</p>@empty<p class="student-empty">Aucun membre ajouté.</p>@endforelse
        @if(auth()->user()->can('research.manage') && $project->principal_researcher_id === auth()->id())
            <form method="POST" action="{{ route('researcher.projects.members', $project->id) }}" class="stack">@csrf<input name="user_id" type="number" placeholder="ID utilisateur" required><input name="role" placeholder="Rôle" required><button class="button button-dark" type="submit">Ajouter un membre</button></form>
        @endif
        <h2>Évaluation</h2>
        @forelse($evaluations as $evaluation)<p><strong>{{ $evaluation->name }}</strong> · {{ $evaluation->status }} · {{ $evaluation->score ?? 'En attente' }}<br>{{ $evaluation->comment }}</p>@empty<p class="student-empty">Aucune évaluation affichée.</p>@endforelse
    </section>
</div>

<div class="student-grid">
    <section class="student-panel">
        <h2>Documents</h2>
        @forelse($documents as $document)<p><strong>{{ $document->title }}</strong> · {{ $document->document_role ?: 'Pièce du projet' }} · <a href="{{ route('researcher.projects.documents.download', [$project->id, $document->id]) }}">Télécharger</a></p>@empty<p class="student-empty">Aucun document ajouté.</p>@endforelse
        @if($project->principal_researcher_id === auth()->id())
            <form method="POST" action="{{ route('researcher.projects.documents', $project->id) }}" enctype="multipart/form-data" class="stack">@csrf<input name="document_role" placeholder="Type de document"><input name="file" type="file" accept="application/pdf,.doc,.docx,.xls,.xlsx" required><button class="button button-dark" type="submit">Ajouter un document</button></form>
        @endif
    </section>
    <section class="student-panel">
        <h2>Publications</h2>
        @forelse($publications as $publication)<p><strong>{{ $publication->title }}</strong> · {{ $publication->doi ?: 'DOI non renseigné' }} · {{ $publication->status }}</p>@empty<p class="student-empty">Aucune publication associée.</p>@endforelse
        @if($project->principal_researcher_id === auth()->id())
            <form method="POST" action="{{ route('researcher.projects.publications.create', $project->id) }}" enctype="multipart/form-data" class="stack">@csrf<input name="title" placeholder="Titre" required><input name="doi" placeholder="DOI"><input name="publication_type" placeholder="Type" required><input name="published_on" type="date"><input name="file" type="file" accept="application/pdf"><button class="button button-lime" type="submit">Ajouter une publication</button></form>
        @endif
    </section>
</div>
@endsection
