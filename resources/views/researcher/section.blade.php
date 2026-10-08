@extends('researcher.layout')
@section('content')
@php
    $sectionInfo = [
        'projects' => ['MES PROJETS', 'Projets accessibles', 'Projets dont vous êtes responsable ou membre.'],
        'applications' => ['CANDIDATURES', 'Mes candidatures', 'Les candidatures de recherche sont suivies à travers le statut de vos projets.'],
        'evaluations' => ['ÉVALUATIONS', 'Évaluations de mes projets', 'Suivi scientifique des projets accessibles à votre compte.'],
        'finance' => ['FINANCEMENT', 'Financements et décaissements', 'Engagements financiers et tranches associés à vos projets.'],
        'documents' => ['DOCUMENTS', 'Documents de mes projets', 'Pièces rattachées aux projets dont vous êtes responsable ou membre.'],
        'notifications' => ['NOTIFICATIONS', 'Notifications récentes', 'Informations adressées à votre compte chercheur.'],
        'calendar' => ['CALENDRIER', 'Échéances et événements', 'Dates des appels, projets et événements FOSER publiés.'],
    ];
    [$sectionLabel, $sectionTitle, $sectionDescription] = $sectionInfo[$section];
@endphp
<div class="rd-section-page-head">
    <div><p class="rd-label">{{ $sectionLabel }}</p><h1>{{ $sectionTitle }}</h1><p>{{ $sectionDescription }}</p></div>
    <a class="rd-page-back" href="{{ route('researcher.dashboard') }}">Retour au tableau de bord</a>
</div>
<nav class="rd-section-tabs" aria-label="Rubriques chercheur">
    @foreach(['projects' => ['Mes projets', 'projects'], 'applications' => ['Mes candidatures', 'applications'], 'evaluations' => ['Évaluations', 'evaluations'], 'finance' => ['Financements', 'finance'], 'documents' => ['Mes documents', 'documents'], 'notifications' => ['Notifications', 'notifications'], 'calendar' => ['Calendrier', 'calendar']] as $tab => [$label, $icon])
        <a href="{{ route('researcher.workspace.section', $tab) }}" @if($section === $tab) aria-current="page" @endif><span class="rd-nav-icon" data-rd-icon="{{ $icon }}" aria-hidden="true"></span><span>{{ $label }}</span></a>
    @endforeach
</nav>
<section class="rd-panel rd-section rd-section-content">
    @if(in_array($section, ['projects', 'applications'], true))
        <p class="rd-section-note">{{ $projectCount }} projet(s) dans votre espace.</p>
        @forelse($projects as $project)
            <article class="rd-list-row"><div><strong>{{ $project->title }}</strong><span>{{ $project->reference }} · Mis à jour le {{ \Carbon\Carbon::parse($project->updated_at)->format('d/m/Y') }}</span></div><div class="rd-row-end"><span class="rd-badge">{{ ucfirst(str_replace('_', ' ', $project->status)) }}</span><a href="{{ route('researcher.projects.show', $project->id) }}">Voir le projet</a></div></article>
        @empty
            <div class="rd-empty"><p>{{ $section === 'applications' ? 'Aucune candidature de recherche pour le moment.' : 'Aucun projet accessible pour le moment.' }}</p><a href="{{ route('researcher.calls') }}">Consulter les appels ouverts</a></div>
        @endforelse
        {{ $projects->links() }}
    @elseif($section === 'evaluations')
        @forelse($evaluations as $evaluation)
            <article class="rd-list-row"><div><strong>{{ $evaluation->project_title }}</strong><span>Mise à jour le {{ \Carbon\Carbon::parse($evaluation->updated_at)->format('d/m/Y') }}</span></div><span class="rd-badge">{{ ucfirst(str_replace('_', ' ', $evaluation->status)) }}</span></article>
        @empty
            <div class="rd-empty"><p>Aucune évaluation enregistrée pour vos projets.</p></div>
        @endforelse
        <p class="rd-note">Les identités des évaluateurs, scores et commentaires ne sont pas affichés dans cet espace.</p>
        {{ $evaluations->links() }}
    @elseif($section === 'finance')
        <h2 class="rd-subheading">Engagements financiers</h2>
        @forelse($commitments as $commitment)
            <article class="rd-list-row"><div><strong>{{ $commitment->project_title }}</strong><span>{{ $commitment->reference }} · {{ \Carbon\Carbon::parse($commitment->committed_at)->format('d/m/Y') }}</span></div><div class="rd-row-end"><strong>{{ number_format($commitment->amount, 0, ',', ' ') }} {{ $commitment->currency }}</strong><span class="rd-badge">{{ ucfirst(str_replace('_', ' ', $commitment->status)) }}</span></div></article>
        @empty
            <div class="rd-empty"><p>Aucun engagement financier enregistré pour vos projets.</p></div>
        @endforelse
        {{ $commitments->links() }}
        <h2 class="rd-subheading rd-subheading-spaced">Décaissements</h2>
        @forelse($disbursements as $disbursement)
            <article class="rd-list-row"><div><strong>{{ $disbursement->project_title }}</strong><span>{{ $disbursement->reference }} · {{ $disbursement->scheduled_for ? \Carbon\Carbon::parse($disbursement->scheduled_for)->format('d/m/Y') : 'Date non planifiée' }}</span></div><div class="rd-row-end"><strong>{{ number_format($disbursement->amount, 0, ',', ' ') }} {{ $disbursement->currency ?: 'FCFA' }}</strong><span class="rd-badge">{{ ucfirst(str_replace('_', ' ', $disbursement->status)) }}</span></div></article>
        @empty
            <div class="rd-empty"><p>Aucun décaissement enregistré pour vos projets.</p></div>
        @endforelse
        {{ $disbursements->links() }}
    @elseif($section === 'documents')
        @forelse($documents as $document)
            <article class="rd-list-row"><div><strong>{{ $document->title }}</strong><span>{{ $document->document_type }} · {{ $document->document_role ?: 'Document de projet' }} · {{ $document->project_title }}</span></div><a href="{{ route('researcher.projects.show', $document->project_id) }}">Ouvrir le projet</a></article>
        @empty
            <div class="rd-empty"><p>Aucun document n’est associé à vos projets.</p></div>
        @endforelse
        {{ $documents->links() }}
    @elseif($section === 'notifications')
        @forelse($notifications as $notification)
            @php($notificationData = json_decode($notification->data, true) ?: [])
            <article class="rd-list-row"><div><strong>{{ $notificationData['subject'] ?? ucfirst(str_replace('.', ' ', $notification->type)) }}</strong><span>{{ $notificationData['message'] ?? 'Une information est disponible dans votre espace.' }}</span><time datetime="{{ \Carbon\Carbon::parse($notification->created_at)->toIso8601String() }}">{{ \Carbon\Carbon::parse($notification->created_at)->format('d/m/Y à H:i') }}</time></div><span class="rd-badge {{ $notification->read_at ? '' : 'rd-badge-warning' }}">{{ $notification->read_at ? 'Lu' : 'Nouveau' }}</span></article>
        @empty
            <div class="rd-empty"><p>Aucune notification adressée à votre compte.</p></div>
        @endforelse
        {{ $notifications->links() }}
    @elseif($section === 'calendar')
        <h2 class="rd-subheading">Clôture des appels</h2>
        @forelse($calls as $call)
            <article class="rd-list-row"><div><strong>{{ $call->title }}</strong><span>{{ $call->program_name }} · Clôture {{ \Carbon\Carbon::parse($call->closes_at)->diffForHumans() }}</span></div><time datetime="{{ \Carbon\Carbon::parse($call->closes_at)->toDateString() }}">{{ \Carbon\Carbon::parse($call->closes_at)->format('d/m/Y') }}</time></article>
        @empty
            <div class="rd-empty"><p>Aucune clôture d’appel à venir.</p></div>
        @endforelse
        <h2 class="rd-subheading rd-subheading-spaced">Échéances de projets</h2>
        @forelse($deadlines as $deadline)
            <article class="rd-list-row"><div><strong>{{ $deadline->title }}</strong><span>Fin prévue · {{ \Carbon\Carbon::parse($deadline->ends_at)->diffForHumans() }}</span></div><time datetime="{{ \Carbon\Carbon::parse($deadline->ends_at)->toDateString() }}">{{ \Carbon\Carbon::parse($deadline->ends_at)->format('d/m/Y') }}</time></article>
        @empty
            <div class="rd-empty"><p>Aucune échéance de projet enregistrée.</p></div>
        @endforelse
        <h2 class="rd-subheading rd-subheading-spaced">Événements FOSER</h2>
        @forelse($events as $event)
            <article class="rd-list-row"><div><strong>{{ $event->title }}</strong><span>{{ $event->venue ?: 'Lieu à préciser' }}</span></div><time datetime="{{ \Carbon\Carbon::parse($event->starts_at)->toIso8601String() }}">{{ \Carbon\Carbon::parse($event->starts_at)->format('d/m/Y à H:i') }}</time></article>
        @empty
            <div class="rd-empty"><p>Aucun événement publié à venir.</p></div>
        @endforelse
    @endif
</section>
@endsection
