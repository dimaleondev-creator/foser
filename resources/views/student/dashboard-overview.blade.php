<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Votre espace personnel étudiant FOSER.">
    <title>Mon espace étudiant | FOSER</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="student-dashboard">
    <a class="student-skip-link" href="#contenu">Aller au contenu</a>
    <button class="student-menu-toggle" type="button" aria-controls="student-sidebar" aria-expanded="false">Menu</button>
    <button class="student-menu-backdrop" type="button" aria-label="Fermer la navigation" tabindex="-1"></button>
    <div class="student-shell">
        <aside class="student-sidebar" id="student-sidebar" aria-label="Navigation étudiant">
            <a class="student-brand" href="{{ route('student.dashboard') }}" aria-label="FOSER, tableau de bord étudiant">
                <x-site-logo-mark />
                <span>Espace étudiant</span>
            </a>
            <nav class="student-sidebar-nav">
                <a class="is-active" href="{{ route('student.dashboard') }}" aria-current="page">Tableau de bord</a>
                <a href="{{ route('student.applications') }}">Mes demandes</a>
                <a href="{{ route('student.programs') }}">Nouvelle demande</a>
                <a href="{{ route('student.documents') }}">Mes documents</a>
                <a href="{{ route('student.profile') }}">Mon profil étudiant</a>
                <a href="{{ route('student.study-loans') }}">Aides et financements</a>
                <a href="{{ route('student.payments') }}">Paiements et décaissements</a>
                <a href="{{ route('student.notifications') }}">Notifications @if($stats['unread'] > 0)<span class="sidebar-count">{{ $stats['unread'] }}</span>@endif</a>
                <a href="{{ route('calls.index') }}">Appels disponibles</a>
                <a href="{{ route('events.index') }}">Calendrier</a>
                <a href="{{ route('student.claims') }}">Réclamations</a>
                <a href="{{ route('student.support') }}">Assistance</a>
                <a href="{{ route('student.profile') }}">Paramètres</a>
            </nav>
            <form class="student-sidebar-logout" method="POST" action="{{ route('auth.logout') }}">
                @csrf
                <button type="submit">Déconnexion</button>
            </form>
            <p class="student-sidebar-caption">Fonds de Soutien à l’Éducation et à la Recherche</p>
        </aside>

        <div class="student-workspace">
            <header class="student-topbar">
                <div>
                    <p class="student-topbar-kicker">ESPACE PERSONNEL</p>
                    <h1>Bonjour, {{ Str::of($user->name)->before(' ')->toString() }}</h1>
                    <p>Bienvenue dans votre espace étudiant FOSER.</p>
                </div>
                <div class="student-topbar-user">
                    <a class="student-notice-link" href="{{ route('student.notifications') }}" aria-label="Notifications, {{ $stats['unread'] }} non lues">Notifications <span>{{ $stats['unread'] }}</span></a>
                    <a class="student-avatar" href="{{ route('student.profile') }}" aria-label="Profil de {{ $user->name }}">{{ Str::of($user->name)->explode(' ')->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}</a>
                    <div><strong>{{ $user->name }}</strong><a href="{{ route('student.profile') }}">Mon profil</a></div>
                </div>
            </header>

            <main id="contenu" class="student-content">
                @if(session('status'))<div class="student-flash" role="status">{{ session('status') }}</div>@endif
                @if($errors->any())<div class="student-error" role="alert">{{ $errors->first() }}</div>@endif

                <section class="student-profile-card" aria-labelledby="student-profile-title">
                    <div class="student-profile-heading">
                        <div class="student-avatar student-avatar-large" aria-hidden="true">{{ Str::of($user->name)->explode(' ')->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}</div>
                        <div><p class="student-section-label">MON PROFIL ÉTUDIANT</p><h2 id="student-profile-title">{{ $user->name }}</h2><p class="student-inee">INEE : {{ $profile?->inee ?: 'Non renseigné' }}</p></div>
                        <div class="student-profile-badges"><span class="student-status-pill {{ $profile?->ine_status === 'verified' ? 'is-success' : 'is-pending' }}">{{ $profile?->ine_status === 'verified' ? 'Profil vérifié' : 'INE en vérification' }}</span><span class="student-status-pill {{ $user->status === 'active' ? 'is-success' : 'is-pending' }}">Compte {{ $user->status === 'active' ? 'actif' : ucfirst($user->status) }}</span></div>
                    </div>
                    <dl class="student-profile-facts">
                        <div><dt>Établissement</dt><dd>{{ $universityName ?: 'Non renseigné' }}</dd></div>
                        <div><dt>Filière</dt><dd>{{ $profile?->program ?: 'Non renseignée' }}</dd></div>
                        <div><dt>Niveau d’étude</dt><dd>{{ $profile?->study_level ?: 'Non renseigné' }}</dd></div>
                        <div><dt>Année académique</dt><dd>{{ $profile?->academic_year ?: 'Non renseignée' }}</dd></div>
                    </dl>
                    @if($stats['profile_completion'] < 100)<div class="student-profile-progress"><span>Profil complété à {{ $stats['profile_completion'] }} %</span><a href="{{ route('student.profile') }}">Compléter mon profil</a></div>@endif
                </section>

                <section class="student-stat-grid" aria-label="Résumé de ma situation">
                    <article class="student-stat"><span>Demandes déposées</span><strong>{{ number_format($stats['total'], 0, ',', ' ') }}</strong><small>Tous programmes confondus</small></article>
                    <article class="student-stat"><span>En traitement</span><strong>{{ number_format($stats['active'], 0, ',', ' ') }}</strong><small>Dossiers non clôturés</small></article>
                    <article class="student-stat"><span>Dossiers acceptés</span><strong>{{ number_format($stats['accepted'], 0, ',', ' ') }}</strong><small>Décisions favorables enregistrées</small></article>
                    <article class="student-stat"><span>Montant attribué</span><strong>{{ number_format($stats['awarded'], 0, ',', ' ') }} <small>FCFA</small></strong><small>Attributions actives</small></article>
                    <article class="student-stat"><span>Montant reçu</span><strong>{{ number_format($stats['total_received'], 0, ',', ' ') }} <small>FCFA</small></strong><small>Paiements confirmés</small></article>
                    <article class="student-stat"><span>À lire</span><strong>{{ number_format($stats['unread'], 0, ',', ' ') }}</strong><small>Notifications non lues</small></article>
                </section>

                @if($stats['missing'] > 0 || $stats['profile_completion'] < 100 || $applications->contains('status', 'complement'))
                    <section class="student-alert" aria-labelledby="student-alert-title">
                        <div><p class="student-section-label">ACTION REQUISE</p><h2 id="student-alert-title">Quelques éléments demandent votre attention.</h2></div>
                        <div class="student-alert-list">
                            @if($stats['missing'] > 0)<p>{{ $stats['missing'] }} pièce(s) requise(s) manquante(s) dans vos dossiers. <a href="{{ route('student.documents') }}">Consulter les documents</a></p>@endif
                            @if($stats['profile_completion'] < 100)<p>Votre profil est complété à {{ $stats['profile_completion'] }} %. <a href="{{ route('student.profile') }}">Mettre à jour mon profil</a></p>@endif
                            @if($applications->contains('status', 'complement'))<p>Un dossier attend des éléments complémentaires. <a href="{{ route('student.applications') }}">Voir mes demandes</a></p>@endif
                        </div>
                    </section>
                @endif

                <div class="student-content-grid">
                    <section class="student-section student-current" aria-labelledby="current-application-title">
                        <div class="student-section-heading"><div><p class="student-section-label">SUIVI</p><h2 id="current-application-title">Ma demande en cours</h2></div><a href="{{ route('student.applications') }}">Tous mes dossiers</a></div>
                        @if($latestApplication)
                            @php
                                $currentStatus = \App\Enums\StudentApplicationStatus::tryFrom($latestApplication->status);
                                $currentProgress = $currentStatus?->progress() ?? 0;
                                $workflowStages = ['Brouillon', 'Documents', 'Soumise', 'Vérification', 'Évaluation', 'Décision', 'Attribution', 'Décaissement'];
                                $currentStage = match ($latestApplication->status) { 'brouillon' => 0, 'soumis' => 2, 'verification', 'recevable', 'incomplet', 'complement' => 3, 'evaluation' => 4, 'valide', 'decision' => 5, 'approuve', 'engage' => 6, 'decaisse', 'paye', 'archive', 'cloture' => 7, default => 0 };
                            @endphp
                            <div class="student-current-meta"><div><h3>{{ $latestApplication->program_name ?: 'Programme non renseigné' }}</h3><p>Référence {{ $latestApplication->reference }}</p></div><span class="student-status-pill">{{ $currentStatus?->label() ?? ucfirst($latestApplication->status) }}</span></div>
                            <ol class="student-timeline" aria-label="Étapes indicatives du traitement"><li class="is-complete">Brouillon</li><li class="is-complete">Documents</li>@foreach(array_slice($workflowStages, 2) as $index => $stage)<li class="{{ $index + 2 < $currentStage ? 'is-complete' : ($index + 2 === $currentStage ? 'is-current' : '') }}" @if($index + 2 === $currentStage) aria-current="step" @endif>{{ $stage }}</li>@endforeach</ol>
                            <div class="student-progress-label"><span>Progression du statut</span><strong>{{ $currentProgress }} %</strong></div><div class="student-progress-track" role="progressbar" aria-label="Progression de la demande" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $currentProgress }}"><span style="width:{{ $currentProgress }}%"></span></div>
                            <a class="student-button student-button-primary" href="{{ route('student.applications') }}">Voir le dossier</a>
                        @else
                            <div class="student-empty"><p>Vous n’avez pas encore de demande.</p><a href="{{ route('student.programs') }}">Découvrir les programmes</a></div>
                        @endif
                    </section>

                    <section class="student-section student-finance" aria-labelledby="student-finance-title">
                        <div class="student-section-heading"><div><p class="student-section-label">FINANCEMENT</p><h2 id="student-finance-title">Mes montants</h2></div><a href="{{ route('student.payments') }}">Historique</a></div>
                        <dl class="student-finance-list">
                            <div><dt>Montant attribué</dt><dd>{{ number_format($stats['awarded'], 0, ',', ' ') }} FCFA</dd></div>
                            <div><dt>Montant reçu</dt><dd>{{ number_format($stats['total_received'], 0, ',', ' ') }} FCFA</dd></div>
                            <div><dt>Solde estimé à recevoir</dt><dd>{{ number_format($stats['pending_disbursement'], 0, ',', ' ') }} FCFA</dd></div>
                        </dl>
                        @php($financeProgress = $stats['awarded'] > 0 ? min(100, (int) round($stats['total_received'] / $stats['awarded'] * 100)) : 0)
                        <div class="student-progress-label"><span>Part reçue des attributions actives</span><strong>{{ $financeProgress }} %</strong></div><div class="student-progress-track" role="progressbar" aria-label="Part reçue des attributions actives" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $financeProgress }}"><span style="width:{{ $financeProgress }}%"></span></div>
                        <p class="student-data-note">Calculé à partir des attributions actives et paiements confirmés liés à vos dossiers.</p>
                    </section>
                </div>

                <section class="student-section" aria-labelledby="recent-applications-title">
                    <div class="student-section-heading"><div><p class="student-section-label">MES DOSSIERS</p><h2 id="recent-applications-title">Demandes récentes</h2></div><a href="{{ route('student.applications') }}">Voir toutes les demandes</a></div>
                    @if($applications->isNotEmpty())
                        <div class="student-table-wrap"><table class="student-table"><thead><tr><th>Programme</th><th>Référence</th><th>Date</th><th>Statut</th><th>Action</th></tr></thead><tbody>@foreach($applications->take(5) as $application)<tr><td data-label="Programme">{{ $application->program_name ?: 'Programme non renseigné' }}</td><td data-label="Référence">{{ $application->reference }}</td><td data-label="Date">{{ \Carbon\Carbon::parse($application->created_at)->format('d/m/Y') }}</td><td data-label="Statut"><span class="student-status-pill">{{ \App\Enums\StudentApplicationStatus::tryFrom($application->status)?->label() ?? ucfirst($application->status) }}</span></td><td data-label="Action"><a href="{{ route('student.applications') }}">Consulter</a></td></tr>@endforeach</tbody></table></div>
                    @else
                        <div class="student-empty"><p>Vous n’avez encore aucune demande.</p><a href="{{ route('student.programs') }}">Découvrir les programmes</a></div>
                    @endif
                </section>

                <section class="student-section" aria-labelledby="quick-actions-title">
                    <div class="student-section-heading"><div><p class="student-section-label">POUR ALLER À L’ESSENTIEL</p><h2 id="quick-actions-title">Actions rapides</h2></div></div>
                    <div class="student-quick-grid"><a href="{{ route('student.programs') }}"><strong>Nouvelle demande</strong><span>Parcourir les programmes disponibles</span></a><a href="{{ route('student.documents') }}"><strong>Ajouter un document</strong><span>Gérer les pièces de vos dossiers</span></a><a href="{{ route('student.profile') }}"><strong>Mettre à jour mon profil</strong><span>Informations personnelles et académiques</span></a><a href="{{ route('calls.index') }}"><strong>Voir les appels</strong><span>Dates et conditions de candidature</span></a><a href="{{ route('student.claims') }}"><strong>Faire une réclamation</strong><span>Suivre une demande d’assistance</span></a><a href="{{ route('student.results') }}"><strong>Mes décisions</strong><span>Consulter les résultats publiés</span></a></div>
                </section>

                <div class="student-content-grid">
                    <section class="student-section" aria-labelledby="student-documents-title"><div class="student-section-heading"><div><p class="student-section-label">PIÈCES JUSTIFICATIVES</p><h2 id="student-documents-title">Mes documents</h2></div><a href="{{ route('student.documents') }}">Tous mes documents</a></div>@forelse($documents as $document)<article class="student-list-row"><div><strong>{{ $document->title }}</strong><span>{{ $document->document_type }} · {{ $document->reference }}</span></div><span class="student-status-pill">{{ ucfirst($document->status) }}</span></article>@empty<div class="student-empty"><p>Aucun document transmis dans vos dossiers.</p><a href="{{ route('student.documents') }}">Voir mes documents</a></div>@endforelse</section>
                    <section class="student-section" aria-labelledby="student-notifications-title"><div class="student-section-heading"><div><p class="student-section-label">À SUIVRE</p><h2 id="student-notifications-title">Notifications récentes</h2></div><a href="{{ route('student.notifications') }}">Toutes les notifications</a></div>@forelse($notifications as $notification)@php($notificationData = is_array($notification->data) ? $notification->data : [])<article class="student-list-row"><div><strong>{{ $notificationData['subject'] ?? ucfirst(str_replace('.', ' ', $notification->type)) }}</strong><span>{{ $notificationData['message'] ?? 'Une information est disponible dans votre espace.' }}</span><time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->format('d/m/Y à H:i') }}</time></div><span class="student-status-pill {{ $notification->read_at ? '' : 'is-pending' }}">{{ $notification->read_at ? 'Lu' : 'Nouveau' }}</span></article>@empty<div class="student-empty"><p>Aucune notification pour le moment.</p></div>@endforelse</section>
                </div>

                <div class="student-content-grid">
                    <section class="student-section" aria-labelledby="student-calls-title"><div class="student-section-heading"><div><p class="student-section-label">CANDIDATURES</p><h2 id="student-calls-title">Appels ouverts</h2></div><a href="{{ route('calls.index') }}">Tous les appels</a></div>@forelse($calls as $call)<article class="student-list-row"><div><strong>{{ $call->title }}</strong><span>{{ $call->program_name ?: 'Programme FOSER' }} · Clôture {{ \Carbon\Carbon::parse($call->closes_at)->format('d/m/Y') }}</span></div><a href="{{ route('calls.show', $call->reference) }}">Consulter</a></article>@empty<div class="student-empty"><p>Aucun appel ouvert actuellement.</p><a href="{{ route('calls.index') }}">Consulter les opportunités</a></div>@endforelse</section>
                    <section class="student-section" aria-labelledby="student-history-title"><div class="student-section-heading"><div><p class="student-section-label">DERNIÈRES ÉTAPES</p><h2 id="student-history-title">Historique récent</h2></div><a href="{{ route('student.applications') }}">Historique complet</a></div>@forelse($history as $event)<article class="student-list-row"><div><strong>{{ \App\Enums\StudentApplicationStatus::tryFrom($event->to_status)?->label() ?? ucfirst($event->to_status) }}</strong>@if($event->reason)<span>{{ $event->reason }}</span>@endif<time datetime="{{ \Carbon\Carbon::parse($event->changed_at)->toIso8601String() }}">{{ \Carbon\Carbon::parse($event->changed_at)->format('d/m/Y à H:i') }}</time></div></article>@empty<div class="student-empty"><p>Aucun historique de traitement disponible.</p></div>@endforelse</section>
                </div>

                <section class="student-section" aria-labelledby="student-calendar-title"><div class="student-section-heading"><div><p class="student-section-label">DATES IMPORTANTES</p><h2 id="student-calendar-title">Calendrier FOSER</h2></div><a href="{{ route('events.index') }}">Tous les événements</a></div>@forelse($events as $event)<article class="student-list-row"><div><strong>{{ $event->title }}</strong><span>{{ $event->venue ?: 'Lieu à préciser' }}</span></div><time datetime="{{ \Carbon\Carbon::parse($event->starts_at)->toIso8601String() }}">{{ \Carbon\Carbon::parse($event->starts_at)->format('d/m/Y à H:i') }}</time></article>@empty<div class="student-empty"><p>Aucun événement FOSER à venir.</p></div>@endforelse</section>

                <footer class="student-footer"><span>© {{ date('Y') }} FOSER · Espace étudiant</span><a href="{{ route('contact') }}">Contacter le FOSER</a></footer>
            </main>
        </div>
    </div>
</body>
</html>