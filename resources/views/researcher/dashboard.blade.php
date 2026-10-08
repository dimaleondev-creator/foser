<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Espace de travail chercheur FOSER">
    <title>Espace chercheur | FOSER</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="research-dashboard">
    <a class="rd-skip-link" href="#rd-content">Aller au contenu</a>
    <button class="rd-menu-toggle" type="button" aria-controls="rd-sidebar" aria-expanded="false">Menu</button>
    <button class="rd-menu-backdrop" type="button" aria-label="Fermer la navigation" tabindex="-1"></button>

    <div class="rd-shell">
        <aside class="rd-sidebar" id="rd-sidebar" aria-label="Navigation chercheur">
            <a class="rd-brand" href="{{ route('researcher.dashboard') }}" aria-label="FOSER, espace chercheur">
                <x-site-logo-mark />
                <span>Recherche &amp; innovation</span>
            </a>
            <nav class="rd-nav">
                <a class="is-active" href="{{ route('researcher.dashboard') }}" aria-current="page"><span class="rd-nav-icon" data-rd-icon="dashboard" aria-hidden="true"></span><span class="rd-nav-label">Tableau de bord</span></a>
                <a href="{{ route('researcher.profile') }}"><span class="rd-nav-icon" data-rd-icon="profile" aria-hidden="true"></span><span class="rd-nav-label">Mon profil chercheur</span></a>
                <a href="{{ route('researcher.workspace.section', 'projects') }}"><span class="rd-nav-icon" data-rd-icon="projects" aria-hidden="true"></span><span class="rd-nav-label">Mes projets</span></a>
                <a href="{{ route('researcher.projects.create-form') }}"><span class="rd-nav-icon" data-rd-icon="newProject" aria-hidden="true"></span><span class="rd-nav-label">Nouveau projet</span></a>
                <a href="{{ route('researcher.calls') }}"><span class="rd-nav-icon" data-rd-icon="calls" aria-hidden="true"></span><span class="rd-nav-label">Appels à projets</span></a>
                <a href="{{ route('researcher.workspace.section', 'applications') }}"><span class="rd-nav-icon" data-rd-icon="applications" aria-hidden="true"></span><span class="rd-nav-label">Mes candidatures</span></a>
                <a href="{{ route('researcher.workspace.section', 'evaluations') }}"><span class="rd-nav-icon" data-rd-icon="evaluations" aria-hidden="true"></span><span class="rd-nav-label">Évaluations</span></a>
                <a href="{{ route('researcher.workspace.section', 'finance') }}"><span class="rd-nav-icon" data-rd-icon="finance" aria-hidden="true"></span><span class="rd-nav-label">Financements</span></a>
                <a href="{{ route('researcher.disbursements') }}"><span class="rd-nav-icon" data-rd-icon="payments" aria-hidden="true"></span><span class="rd-nav-label">Décaissements</span></a>
                <a href="{{ route('researcher.workspace.section', 'documents') }}"><span class="rd-nav-icon" data-rd-icon="documents" aria-hidden="true"></span><span class="rd-nav-label">Mes documents</span></a>
                <a href="{{ route('researcher.publications') }}"><span class="rd-nav-icon" data-rd-icon="publications" aria-hidden="true"></span><span class="rd-nav-label">Mes publications</span></a>
                <a href="{{ route('researcher.workspace.section', 'notifications') }}"><span class="rd-nav-icon" data-rd-icon="notifications" aria-hidden="true"></span><span class="rd-nav-label">Notifications</span>@if($stats['unread_notifications'] > 0)<span class="rd-count">{{ $stats['unread_notifications'] }}</span>@endif</a>
                <a href="{{ route('researcher.workspace.section', 'calendar') }}"><span class="rd-nav-icon" data-rd-icon="calendar" aria-hidden="true"></span><span class="rd-nav-label">Calendrier</span></a>
                <a href="{{ route('contact') }}"><span class="rd-nav-icon" data-rd-icon="support" aria-hidden="true"></span><span class="rd-nav-label">Assistance FOSER</span></a>
                <a href="{{ route('researcher.profile') }}"><span class="rd-nav-icon" data-rd-icon="settings" aria-hidden="true"></span><span class="rd-nav-label">Paramètres du profil</span></a>
            </nav>
            <form class="rd-logout" method="POST" action="{{ route('auth.logout') }}">@csrf<button type="submit"><span class="rd-nav-icon" data-rd-icon="logout" aria-hidden="true"></span><span>Déconnexion</span></button></form>
            <p class="rd-sidebar-caption">Fonds de Soutien à l’Éducation et à la Recherche</p>
        </aside>

        <div class="rd-workspace">
            <header class="rd-header">
                <div class="rd-greeting">
                    <p class="rd-eyebrow">ESPACE DE RECHERCHE</p>
                    <h1>Bonjour, {{ $profile?->academic_rank ? $profile->academic_rank.' ' : '' }}{{ $researcher->name }}</h1>
                    <p>Bienvenue dans votre espace chercheur FOSER.</p>
                </div>
                <div class="rd-user-tools">
                    <a class="rd-notification-link" href="{{ route('researcher.workspace.section', 'notifications') }}">Notifications <span>{{ $stats['unread_notifications'] }}</span></a>
                    <a class="rd-avatar" href="{{ route('researcher.profile') }}" aria-label="Profil de {{ $researcher->name }}">{{ \Illuminate\Support\Str::of($researcher->name)->explode(' ')->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}</a>
                    <div class="rd-user-name"><strong>{{ $researcher->name }}</strong><span>Compte chercheur validé</span></div>
                    <a class="rd-primary-button" href="{{ route('researcher.projects.create-form') }}"><span class="rd-nav-icon" data-rd-icon="newProject" aria-hidden="true"></span>Nouveau projet</a>
                </div>
            </header>

            <main class="rd-content" id="rd-content">
                @if(session('status'))<div class="rd-message" role="status">{{ session('status') }}</div>@endif
                @if($errors->any())<div class="rd-error" role="alert">{{ $errors->first() }}</div>@endif

                <section class="rd-profile-card" aria-labelledby="rd-profile-heading">
                    <div class="rd-profile-top">
                        <div class="rd-avatar rd-avatar-large" aria-hidden="true">{{ \Illuminate\Support\Str::of($researcher->name)->explode(' ')->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}</div>
                        <div class="rd-profile-title"><p class="rd-label">PROFIL CHERCHEUR</p><h2 id="rd-profile-heading">{{ $researcher->name }}</h2><p>{{ $profile?->academic_rank ?: 'Grade à renseigner' }} · {{ $profile?->research_domain ?: 'Domaine de recherche à renseigner' }}</p></div>
                        <div class="rd-profile-status"><span class="rd-badge rd-badge-success">Compte validé</span><span class="rd-badge {{ $profileCompletion === 100 ? 'rd-badge-success' : 'rd-badge-warning' }}">{{ $profileCompletion === 100 ? 'Profil complété' : 'Profil à compléter' }}</span></div>
                    </div>
                    <dl class="rd-profile-details">
                        <div><dt>Institution</dt><dd>{{ $university?->name ?: 'Non renseignée' }}</dd></div>
                        <div><dt>Laboratoire</dt><dd>{{ $laboratory?->name ?: 'Non renseigné' }}</dd></div>
                        <div><dt>Courriel</dt><dd>{{ $researcher->email }}</dd></div>
                        <div><dt>Téléphone</dt><dd>{{ $profile?->phone ?: 'Non renseigné' }}</dd></div>
                        <div><dt>Identifiant chercheur</dt><dd>{{ $profile?->researcher_number ?: 'Non attribué' }}</dd></div>
                        <div><dt>Complétude scientifique</dt><dd>{{ $profileCompletion }} %</dd></div>
                    </dl>
                    @if($profileCompletion < 100)<div class="rd-profile-action"><span>Complétez vos informations pour tenir votre profil scientifique à jour.</span><a href="{{ route('researcher.profile') }}">Modifier mon profil</a></div>@endif
                </section>

                <section class="rd-stats-grid" aria-label="Indicateurs de recherche">
                    <article class="rd-stat"><span><span class="rd-stat-icon" data-rd-icon="projects" aria-hidden="true"></span>Projets accessibles</span><strong>{{ number_format($stats['total_projects'], 0, ',', ' ') }}</strong><small>Projets dont vous êtes responsable ou membre</small></article>
                    <article class="rd-stat"><span><span class="rd-stat-icon" data-rd-icon="applications" aria-hidden="true"></span>Candidatures soumises</span><strong>{{ number_format($stats['submitted_projects'], 0, ',', ' ') }}</strong><small>Projets transmis pour traitement</small></article>
                    <article class="rd-stat"><span><span class="rd-stat-icon" data-rd-icon="active" aria-hidden="true"></span>Projets actifs</span><strong>{{ number_format($stats['active_projects'], 0, ',', ' ') }}</strong><small>Financés ou en cours d’exécution</small></article>
                    <article class="rd-stat"><span><span class="rd-stat-icon" data-rd-icon="funded" aria-hidden="true"></span>Projets financés</span><strong>{{ number_format($stats['funded_projects'], 0, ',', ' ') }}</strong><small>Statut de financement enregistré</small></article>
                    <article class="rd-stat"><span><span class="rd-stat-icon" data-rd-icon="finance" aria-hidden="true"></span>Financement engagé</span><strong>{{ number_format($stats['awarded_amount'], 0, ',', ' ') }} <small>FCFA</small></strong><small>Engagements approuvés</small></article>
                    <article class="rd-stat"><span><span class="rd-stat-icon" data-rd-icon="payments" aria-hidden="true"></span>Décaissements effectués</span><strong>{{ number_format($stats['disbursed_amount'], 0, ',', ' ') }} <small>FCFA</small></strong><small>Tranches traitées ou payées</small></article>
                </section>

                <section class="rd-panel rd-status-chart" aria-labelledby="rd-chart-heading"><div class="rd-section-heading"><div><p class="rd-label">PORTEFEUILLE SCIENTIFIQUE</p><h2 id="rd-chart-heading">Répartition par statut</h2></div><span class="rd-chart-total">{{ number_format($stats['total_projects'], 0, ',', ' ') }} projets</span></div>@if($statusDistribution->isNotEmpty())<div class="rd-chart-list">@foreach($statusDistribution as $row)<div class="rd-chart-row"><span>{{ $row->status_label }}</span><div class="rd-chart-track" role="img" aria-label="{{ $row->status_label }} : {{ $row->total }}"><span style="width:{{ ((int) $row->total / $statusMaximum) * 100 }}%"></span></div><strong>{{ number_format($row->total, 0, ',', ' ') }}</strong></div>@endforeach</div>@else<div class="rd-empty"><p>La répartition apparaîtra dès qu’un projet sera enregistré.</p></div>@endif</section>

                @if($stats['pending_actions'] > 0 || $profileCompletion < 80)
                    <section class="rd-alert" aria-labelledby="rd-alert-heading">
                        <div><p class="rd-label">À FAIRE</p><h2 id="rd-alert-heading">Actions à suivre</h2></div>
                        <div class="rd-alert-items">
                            @if($stats['pending_actions'] > 0)<p>{{ $stats['pending_actions'] }} projet(s) au brouillon ou en demande de complément. <a href="#rd-projects">Consulter mes projets</a></p>@endif
                            @if($profileCompletion < 80)<p>Votre profil scientifique est complété à {{ $profileCompletion }} %. <a href="{{ route('researcher.profile') }}">Compléter mon profil</a></p>@endif
                        </div>
                    </section>
                @endif

                <div class="rd-feature-grid">
                    <section class="rd-panel rd-main-project" aria-labelledby="rd-main-project-heading">
                        <div class="rd-section-heading"><div><p class="rd-label">SUIVI DU PROJET</p><h2 id="rd-main-project-heading">Projet de recherche en cours</h2></div><a href="#rd-projects">Tous mes projets</a></div>
                        @if($mainProject)
                            <div class="rd-project-title-row"><div><h3>{{ $mainProject->title }}</h3><p>{{ $mainProject->reference }} · {{ $mainProject->program_name ?: 'Programme non renseigné' }}</p></div><span class="rd-badge">{{ $mainProject->status_label }}</span></div>
                            <dl class="rd-project-facts"><div><dt>Responsable</dt><dd>{{ $mainProject->principal_researcher_name ?: $researcher->name }}</dd></div><div><dt>Institution</dt><dd>{{ $mainProject->university_name ?: 'Non renseignée' }}</dd></div><div><dt>Domaine</dt><dd>{{ $mainProject->research_area ?: $mainProject->domain ?: 'Non renseigné' }}</dd></div><div><dt>Échéance prévue</dt><dd>{{ $mainProject->ends_at ? \Carbon\Carbon::parse($mainProject->ends_at)->format('d/m/Y') : 'Non renseignée' }}</dd></div></dl>
                            <ol class="rd-timeline" aria-label="Progression indicative du projet">@foreach($workflowStages as $index => $stage)<li class="{{ $index < $mainProject->workflow_step ? 'is-complete' : ($index === $mainProject->workflow_step ? 'is-current' : '') }}" @if($index === $mainProject->workflow_step) aria-current="step" @endif>{{ $stage }}</li>@endforeach</ol>
                            <p class="rd-current-stage">Étape actuelle : <strong>{{ $workflowStages[$mainProject->workflow_step] }}</strong></p>
                            <div class="rd-main-project-finance"><span>Budget demandé <strong>{{ $mainProject->budget !== null ? number_format($mainProject->budget, 0, ',', ' ').' '.($mainProject->currency ?: 'FCFA') : 'Non renseigné' }}</strong></span><span>Financement engagé <strong>{{ number_format($mainProjectAwarded, 0, ',', ' ') }} FCFA</strong></span><span>Décaissé <strong>{{ number_format($mainProjectDisbursed, 0, ',', ' ') }} FCFA</strong></span></div>
                            <a class="rd-button" href="{{ route('researcher.projects.show', $mainProject->id) }}">Voir le projet</a>
                        @else
                            <div class="rd-empty"><p>Vous n’avez pas encore de projet de recherche.</p><a href="{{ route('researcher.calls') }}">Découvrir les appels ouverts</a></div>
                        @endif
                    </section>

                    <section class="rd-panel rd-finance-panel" id="rd-finance" aria-labelledby="rd-finance-heading">
                        <div class="rd-section-heading"><div><p class="rd-label">FINANCEMENT</p><h2 id="rd-finance-heading">Situation financière</h2></div><a href="{{ route('researcher.disbursements') }}">Décaissements</a></div>
                        <dl class="rd-money-list"><div><dt>Budget demandé</dt><dd>{{ number_format($stats['requested_amount'], 0, ',', ' ') }} FCFA</dd></div><div><dt>Montant engagé</dt><dd>{{ number_format($stats['awarded_amount'], 0, ',', ' ') }} FCFA</dd></div><div><dt>Montant décaissé</dt><dd>{{ number_format($stats['disbursed_amount'], 0, ',', ' ') }} FCFA</dd></div><div><dt>Solde engagé estimé</dt><dd>{{ number_format(max(0, $stats['awarded_amount'] - $stats['disbursed_amount']), 0, ',', ' ') }} FCFA</dd></div></dl>
                        <div class="rd-progress-label"><span>Part décaissée des engagements</span><strong>{{ $stats['finance_progress'] }} %</strong></div><div class="rd-progress-track" role="progressbar" aria-label="Part décaissée des engagements" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $stats['finance_progress'] }}"><span style="width:{{ $stats['finance_progress'] }}%"></span></div>
                        <p class="rd-note">Calcul établi à partir des engagements approuvés et des tranches liées à vos projets.</p>
                    </section>
                </div>

                <section class="rd-panel rd-section" id="rd-projects" aria-labelledby="rd-projects-heading">
                    <div class="rd-section-heading"><div><p class="rd-label">VOTRE PORTEFEUILLE</p><h2 id="rd-projects-heading">Mes projets et candidatures</h2><p class="rd-section-note">Les candidatures de recherche sont suivies par le statut de chaque projet.</p></div><a href="{{ route('researcher.projects.create-form') }}">Nouveau projet</a></div>
                    @forelse($projects as $project)
                        <article class="rd-project-row"><div class="rd-project-row-main"><span class="rd-project-ref">{{ $project->reference }}</span><h3><a href="{{ route('researcher.projects.show', $project->id) }}">{{ $project->title }}</a></h3><p>{{ $project->research_area ?: $project->domain ?: 'Domaine non renseigné' }} · {{ $project->program_name ?: 'Programme non renseigné' }}</p></div><div class="rd-project-row-meta"><span class="rd-badge">{{ $project->status_label }}</span><span>{{ $project->budget !== null ? number_format($project->budget, 0, ',', ' ').' '.($project->currency ?: 'FCFA') : 'Budget non renseigné' }}</span><a href="{{ route('researcher.projects.show', $project->id) }}">Détails</a></div></article>
                    @empty
                        <div class="rd-empty"><p>Aucun projet à afficher pour le moment.</p><a href="{{ route('researcher.calls') }}">Voir les appels à projets</a></div>
                    @endforelse
                </section>

                <div class="rd-feature-grid rd-secondary-grid">
                    <section class="rd-panel rd-section" id="rd-calls" aria-labelledby="rd-calls-heading"><div class="rd-section-heading"><div><p class="rd-label">OPPORTUNITÉS</p><h2 id="rd-calls-heading">Appels à projets ouverts</h2></div><a href="{{ route('researcher.calls') }}">Tous les appels</a></div>@forelse($openCalls as $call)<article class="rd-list-row"><div><strong>{{ $call->title }}</strong><span>{{ $call->program_name }} · @if($call->domains){{ $call->domains }} · @endif Clôture {{ \Carbon\Carbon::parse($call->closes_at)->format('d/m/Y') }}</span>@if($call->maximum_project_amount)<small>Plafond : {{ number_format($call->maximum_project_amount, 0, ',', ' ') }} FCFA</small>@endif</div><a href="{{ route('calls.show', $call->reference) }}">Consulter</a></article>@empty<div class="rd-empty"><p>Aucun appel de recherche n’est ouvert actuellement.</p><a href="{{ route('researcher.calls') }}">Consulter les opportunités</a></div>@endforelse</section>

                    <section class="rd-panel rd-section" id="rd-evaluations" aria-labelledby="rd-evaluations-heading"><div class="rd-section-heading"><div><p class="rd-label">SUIVI SCIENTIFIQUE</p><h2 id="rd-evaluations-heading">Évaluations</h2></div></div>@forelse($evaluations as $evaluation)<article class="rd-list-row"><div><strong>{{ $evaluation->project_title }}</strong><span>Dernière mise à jour : {{ \Carbon\Carbon::parse($evaluation->updated_at)->format('d/m/Y') }}</span></div><span class="rd-badge">{{ ucfirst(str_replace('_', ' ', $evaluation->status)) }}</span></article>@empty<div class="rd-empty"><p>Aucune évaluation enregistrée pour vos projets.</p></div>@endforelse<p class="rd-note">Les identités des évaluateurs et les commentaires confidentiels ne sont pas affichés ici.</p></section>
                </div>

                <div class="rd-feature-grid rd-secondary-grid">
                    <section class="rd-panel rd-section" id="rd-documents" aria-labelledby="rd-documents-heading"><div class="rd-section-heading"><div><p class="rd-label">PIÈCES DE PROJET</p><h2 id="rd-documents-heading">Documents récents</h2></div>@if($mainProject)<a href="{{ route('researcher.projects.show', $mainProject->id) }}">Gérer les pièces</a>@endif</div>@forelse($documents as $document)<article class="rd-list-row"><div><strong>{{ $document->title }}</strong><span>{{ $document->document_type }} · {{ $document->project_title }}</span></div><a href="{{ route('researcher.projects.show', $document->project_id) }}">Consulter</a></article>@empty<div class="rd-empty"><p>Aucun document n’est associé à vos projets.</p>@if($mainProject)<a href="{{ route('researcher.projects.show', $mainProject->id) }}">Ouvrir le projet principal</a>@endif</div>@endforelse</section>

                    <section class="rd-panel rd-section" id="rd-disbursements" aria-labelledby="rd-disbursements-heading"><div class="rd-section-heading"><div><p class="rd-label">VERSEMENTS</p><h2 id="rd-disbursements-heading">Derniers décaissements</h2></div><a href="{{ route('researcher.disbursements') }}">Tout l’historique</a></div>@forelse($latestDisbursements as $disbursement)<article class="rd-list-row"><div><strong>{{ $disbursement->project_title }}</strong><span>{{ $disbursement->reference }} · {{ $disbursement->scheduled_for ? \Carbon\Carbon::parse($disbursement->scheduled_for)->format('d/m/Y') : 'Date non planifiée' }}</span></div><div class="rd-row-end"><strong>{{ number_format($disbursement->amount, 0, ',', ' ') }} {{ $disbursement->currency ?: 'FCFA' }}</strong><span class="rd-badge">{{ ucfirst(str_replace('_', ' ', $disbursement->status)) }}</span></div></article>@empty<div class="rd-empty"><p>Aucun décaissement lié à vos projets.</p></div>@endforelse</section>
                </div>

                <div class="rd-feature-grid rd-secondary-grid">
                    <section class="rd-panel rd-section" id="rd-publications" aria-labelledby="rd-publications-heading"><div class="rd-section-heading"><div><p class="rd-label">PRODUCTION SCIENTIFIQUE</p><h2 id="rd-publications-heading">Mes publications</h2></div><a href="{{ route('researcher.publications') }}">Gérer mes publications</a></div>@forelse($publications as $publication)<article class="rd-list-row"><div><strong>{{ $publication->title }}</strong><span>{{ ucfirst($publication->publication_type) }} · {{ $publication->published_on ? \Carbon\Carbon::parse($publication->published_on)->format('Y') : 'Année non renseignée' }}</span></div><span class="rd-badge">{{ ucfirst($publication->status) }}</span></article>@empty<div class="rd-empty"><p>Aucune publication enregistrée à votre nom.</p><a href="{{ route('researcher.publications') }}">Ajouter une publication</a></div>@endforelse</section>

                    <section class="rd-panel rd-section" id="rd-notifications" aria-labelledby="rd-notifications-heading"><div class="rd-section-heading"><div><p class="rd-label">ACTUALITÉS DU COMPTE</p><h2 id="rd-notifications-heading">Notifications récentes</h2></div></div>@forelse($notifications as $notification)<article class="rd-list-row"><div><strong>{{ (json_decode($notification->data, true) ?: [])['subject'] ?? ucfirst(str_replace('.', ' ', $notification->type)) }}</strong><span>{{ (json_decode($notification->data, true) ?: [])['message'] ?? 'Une information est disponible dans votre espace.' }}</span><time datetime="{{ \Carbon\Carbon::parse($notification->created_at)->toIso8601String() }}">{{ \Carbon\Carbon::parse($notification->created_at)->format('d/m/Y à H:i') }}</time></div><span class="rd-badge {{ $notification->read_at ? '' : 'rd-badge-warning' }}">{{ $notification->read_at ? 'Lu' : 'Nouveau' }}</span></article>@empty<div class="rd-empty"><p>Aucune notification pour le moment.</p></div>@endforelse</section>
                </div>

                <section class="rd-panel rd-section" id="rd-calendar" aria-labelledby="rd-calendar-heading"><div class="rd-section-heading"><div><p class="rd-label">DATES À RETENIR</p><h2 id="rd-calendar-heading">Calendrier de recherche</h2></div><a href="{{ route('events.index') }}">Agenda FOSER</a></div><div class="rd-calendar-grid"><div><h3>Clôture des appels</h3>@forelse($openCalls->take(4) as $call)<article class="rd-list-row"><div><strong>{{ $call->title }}</strong><span>{{ \Carbon\Carbon::parse($call->closes_at)->diffForHumans() }}</span></div><time datetime="{{ \Carbon\Carbon::parse($call->closes_at)->toDateString() }}">{{ \Carbon\Carbon::parse($call->closes_at)->format('d/m/Y') }}</time></article>@empty<p class="rd-empty-copy">Aucune échéance d’appel à venir.</p>@endforelse</div><div><h3>Échéances de projets</h3>@forelse($upcomingProjectDeadlines as $deadline)<article class="rd-list-row"><div><strong>{{ $deadline->title }}</strong><span>Fin prévue · {{ \Carbon\Carbon::parse($deadline->ends_at)->diffForHumans() }}</span></div><time datetime="{{ \Carbon\Carbon::parse($deadline->ends_at)->toDateString() }}">{{ \Carbon\Carbon::parse($deadline->ends_at)->format('d/m/Y') }}</time></article>@empty<p class="rd-empty-copy">Aucune échéance de projet enregistrée.</p>@endforelse</div><div><h3>Événements FOSER</h3>@forelse($events as $event)<article class="rd-list-row"><div><strong>{{ $event->title }}</strong><span>{{ $event->venue ?: 'Lieu à préciser' }}</span></div><time datetime="{{ \Carbon\Carbon::parse($event->starts_at)->toIso8601String() }}">{{ \Carbon\Carbon::parse($event->starts_at)->format('d/m/Y') }}</time></article>@empty<p class="rd-empty-copy">Aucun événement publié à venir.</p>@endforelse</div></div></section>

                <section class="rd-panel rd-section rd-report-note" aria-labelledby="rd-reports-heading"><div><p class="rd-label">SUIVI CONTRACTUEL</p><h2 id="rd-reports-heading">Rapports de recherche</h2><p>Les échéances de rapports ne sont pas gérées dans le système actuel. Aucune date de remise n’est affichée sans donnée source.</p></div></section>

                <footer class="rd-footer"><span>© {{ date('Y') }} FOSER · Espace chercheur</span><a href="{{ route('contact') }}">Contacter le FOSER</a><a href="{{ route('researcher.profile') }}">Paramètres du profil</a></footer>
            </main>
        </div>
    </div>
</body>
</html>