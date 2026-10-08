<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Espace de suivi de votre établissement universitaire FOSER">
    <title>Espace université | FOSER</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="university-dashboard">
    <a class="ud-skip-link" href="#ud-content">Aller au contenu</a>
    <button class="ud-menu-toggle" type="button" aria-controls="ud-sidebar" aria-expanded="false" aria-label="Ouvrir la navigation">Menu</button>
    <button class="ud-menu-backdrop" type="button" aria-label="Fermer la navigation" tabindex="-1"></button>

    <div class="ud-shell">
        <aside class="ud-sidebar" id="ud-sidebar" aria-label="Navigation établissement">
            <a class="ud-brand" href="{{ route('university.dashboard') }}" aria-label="FOSER, espace université">
                <x-site-logo-mark />
                <span>Portail établissement</span>
            </a>
            <nav class="ud-nav">
                <a class="is-active" href="{{ route('university.dashboard') }}" aria-current="page"><span class="ud-icon" data-ud-icon="dashboard" aria-hidden="true"></span><span class="ud-nav-label">Tableau de bord</span></a>
                <a href="{{ route('university.profile') }}"><span class="ud-icon" data-ud-icon="institution" aria-hidden="true"></span><span class="ud-nav-label">Mon établissement</span></a>
                @if(auth()->user()->can('university.students.view'))<a href="{{ route('university.students') }}"><span class="ud-icon" data-ud-icon="students" aria-hidden="true"></span><span class="ud-nav-label">Étudiants</span></a>@endif
                @if(auth()->user()->can('university.applications.validate'))<a href="{{ route('university.applications') }}"><span class="ud-icon" data-ud-icon="applications" aria-hidden="true"></span><span class="ud-nav-label">Dossiers</span>@if($pendingApplications > 0)<span class="ud-count">{{ $pendingApplications }}</span>@endif</a>@endif
                <a href="{{ route('university.laboratories') }}"><span class="ud-icon" data-ud-icon="laboratories" aria-hidden="true"></span><span class="ud-nav-label">Laboratoires</span></a>
                @if(auth()->user()->can('university.imports.create'))<a href="{{ route('university.imports') }}"><span class="ud-icon" data-ud-icon="imports" aria-hidden="true"></span><span class="ud-nav-label">Imports étudiants</span></a>@endif
                @if(auth()->user()->can('university.reports.view'))<a href="{{ route('university.reports') }}"><span class="ud-icon" data-ud-icon="reports" aria-hidden="true"></span><span class="ud-nav-label">Rapports</span></a>@endif
                @if(auth()->user()->can('university.messages.create'))<a href="{{ route('university.messages.index') }}"><span class="ud-icon" data-ud-icon="messages" aria-hidden="true"></span><span class="ud-nav-label">Messagerie</span>@if($unreadMessages > 0)<span class="ud-count">{{ $unreadMessages }}</span>@endif</a>@endif
                @if(auth()->user()->can('university.manage'))<a href="{{ route('university.users') }}"><span class="ud-icon" data-ud-icon="users" aria-hidden="true"></span><span class="ud-nav-label">Accès utilisateurs</span></a>@endif
                <a href="{{ route('university.profile') }}"><span class="ud-icon" data-ud-icon="settings" aria-hidden="true"></span><span class="ud-nav-label">Paramètres</span></a>
            </nav>
            <form class="ud-logout" method="POST" action="{{ route('auth.logout') }}">@csrf<button type="submit"><span class="ud-icon" data-ud-icon="logout" aria-hidden="true"></span><span>Déconnexion</span></button></form>
            <p class="ud-sidebar-caption">Fonds de Soutien à l’Éducation et à la Recherche</p>
        </aside>

        <div class="ud-workspace">
            <header class="ud-header">
                <div class="ud-greeting">
                    <p class="ud-eyebrow">ESPACE ÉTABLISSEMENT</p>
                    <h1>{{ $university?->name ?: 'Mon établissement' }}</h1>
                    <p>{{ $university?->institution_type === 'private' ? 'Établissement privé' : 'Établissement public' }} · {{ $university?->city ?: 'Ville non renseignée' }}{{ $university?->region ? ' · '.$university->region : '' }}</p>
                </div>
                <div class="ud-user-tools">
                    <a class="ud-notification-link" href="#ud-notifications" aria-label="Notifications, {{ $unreadNotifications }} non lues">Notifications <span aria-hidden="true">{{ $unreadNotifications }}</span></a>
                    <span class="ud-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}</span>
                    <div class="ud-user-name"><strong>{{ auth()->user()->name }}</strong><span>{{ $university?->responsible_function ?: 'Responsable établissement' }}</span></div>
                </div>
            </header>

            <main class="ud-content" id="ud-content">
                @if(session('status'))<div class="ud-message" role="status">{{ session('status') }}</div>@endif
                @if($errors->any())<div class="ud-error" role="alert">{{ $errors->first() }}</div>@endif

                <section class="ud-intro-row">
                    <div><p class="ud-label">VUE D’ENSEMBLE</p><h2>Suivi de l’établissement</h2><p>Bonjour {{ auth()->user()->name }}. Voici la situation actuelle de votre établissement.</p></div>
                    <nav class="ud-quick-actions" aria-label="Accès rapides">
                        @if(auth()->user()->can('university.students.view'))<a class="ud-button ud-button-primary" href="{{ route('university.students') }}">Étudiants</a>@endif
                        @if(auth()->user()->can('university.applications.validate'))<a class="ud-button ud-button-secondary" href="{{ route('university.applications') }}">Dossiers</a>@endif
                        @if(auth()->user()->can('university.imports.create'))<a class="ud-button ud-button-secondary" href="{{ route('university.imports') }}">Imports</a>@endif
                        @if(auth()->user()->can('university.reports.view'))<a class="ud-button ud-button-secondary" href="{{ route('university.reports') }}">Rapports</a>@endif
                        <a class="ud-button ud-button-secondary" href="{{ route('university.profile') }}">Profil</a>
                        @if(auth()->user()->can('university.messages.create'))<a class="ud-button ud-button-secondary" href="{{ route('university.messages.index') }}">Messages</a>@endif
                    </nav>
                </section>

                @if($studentsPendingValidation > 0 || $pendingApplications > 0 || $incompleteApplications > 0)
                    <section class="ud-action-strip" aria-labelledby="ud-action-heading">
                        <div><p class="ud-label">SUIVI PRIORITAIRE</p><h2 id="ud-action-heading">Éléments à traiter</h2></div>
                        <div class="ud-action-items">
                            @if($studentsPendingValidation > 0 && auth()->user()->can('university.students.view'))<a href="{{ route('university.students', ['status' => 'pending']) }}"><strong>{{ number_format($studentsPendingValidation, 0, ',', ' ') }}</strong><span>profil(s) étudiant à vérifier</span><b>Ouvrir</b></a>@endif
                            @if($pendingApplications > 0 && auth()->user()->can('university.applications.validate'))<a href="{{ route('university.applications', ['status' => 'soumis']) }}"><strong>{{ number_format($pendingApplications, 0, ',', ' ') }}</strong><span>dossier(s) nécessitant un suivi</span><b>Examiner</b></a>@endif
                            @if($incompleteApplications > 0 && auth()->user()->can('university.applications.validate'))<a href="{{ route('university.applications', ['status' => 'incomplet']) }}"><strong>{{ number_format($incompleteApplications, 0, ',', ' ') }}</strong><span>dossier(s) incomplet(s) ou en correction</span><b>Consulter</b></a>@endif
                        </div>
                    </section>
                @endif

                <section class="ud-stat-grid" aria-label="Indicateurs de l’établissement">
                    <article class="ud-stat"><span><span class="ud-stat-icon" data-ud-icon="students" aria-hidden="true"></span>Étudiants enregistrés</span><strong>{{ number_format($studentsCount, 0, ',', ' ') }}</strong><small>Inscrits dans votre établissement</small></article>
                    <article class="ud-stat"><span><span class="ud-stat-icon" data-ud-icon="active" aria-hidden="true"></span>Étudiants actifs</span><strong>{{ number_format($activeStudents, 0, ',', ' ') }}</strong><small>Comptes étudiants actifs</small></article>
                    <article class="ud-stat"><span><span class="ud-stat-icon" data-ud-icon="applications" aria-hidden="true"></span>Dossiers reçus</span><strong>{{ number_format($applicationsCount, 0, ',', ' ') }}</strong><small>Tous statuts confondus</small></article>
                    <article class="ud-stat ud-stat-priority"><span><span class="ud-stat-icon" data-ud-icon="review" aria-hidden="true"></span>Dossiers à suivre</span><strong>{{ number_format($pendingApplications, 0, ',', ' ') }}</strong><small>Soumis, en vérification ou incomplets</small></article>
                    <article class="ud-stat"><span><span class="ud-stat-icon" data-ud-icon="validated" aria-hidden="true"></span>Dossiers validés</span><strong>{{ number_format($validatedApplications, 0, ',', ' ') }}</strong><small>Décision d’éligibilité enregistrée</small></article>
                    <article class="ud-stat"><span><span class="ud-stat-icon" data-ud-icon="beneficiaries" aria-hidden="true"></span>Bénéficiaires</span><strong>{{ number_format($beneficiaries, 0, ',', ' ') }}</strong><small>Attributions distinctes enregistrées</small></article>
                </section>

                <div class="ud-main-grid">
                    <section class="ud-panel" aria-labelledby="ud-applications-heading">
                        <div class="ud-section-heading"><div><p class="ud-label">DERNIÈRE ACTIVITÉ</p><h2 id="ud-applications-heading">Dossiers récents</h2></div>@if(auth()->user()->can('university.applications.validate'))<a href="{{ route('university.applications') }}">Tous les dossiers</a>@endif</div>
                        @forelse($recentApplications as $application)
                            <article class="ud-record-row"><div><strong>{{ $application->student_name }}</strong><span>{{ $application->program_name ?: 'Programme non renseigné' }} · {{ $application->reference }}</span></div><div class="ud-record-meta"><span class="ud-badge {{ in_array($application->status, ['incomplet', 'complement']) ? 'is-warning' : '' }}">{{ ucfirst(str_replace('_', ' ', $application->status)) }}</span><time datetime="{{ \Carbon\Carbon::parse($application->updated_at)->toIso8601String() }}">{{ \Carbon\Carbon::parse($application->updated_at)->format('d/m/Y') }}</time>@if(auth()->user()->can('university.applications.validate'))<a href="{{ route('university.applications.show', $application->id) }}">Consulter</a>@endif</div></article>
                        @empty
                            <div class="ud-empty"><p>Aucun dossier étudiant reçu pour le moment.</p>@if(auth()->user()->can('university.students.view'))<a href="{{ route('university.students') }}">Consulter la liste des étudiants</a>@endif</div>
                        @endforelse
                    </section>

                    @if(auth()->user()->can('university.imports.create'))<section class="ud-panel ud-import-panel" aria-labelledby="ud-import-heading">
                        <div class="ud-section-heading"><div><p class="ud-label">GESTION DES INSCRIPTIONS</p><h2 id="ud-import-heading">Import des étudiants</h2></div></div>
                        <p>Ajoutez les étudiants de votre établissement depuis un fichier conforme au modèle FOSER.</p>
                        @if($studentsCount === 0)<div class="ud-empty-inline">Aucun étudiant n’est encore rattaché à votre université.</div>@endif
                        @if($lastImport)<div class="ud-import-summary"><strong>Dernier import</strong><span>{{ $lastImport->filename }}</span><small>{{ \Carbon\Carbon::parse($lastImport->created_at)->format('d/m/Y à H:i') }} · {{ number_format($lastImport->imported_count, 0, ',', ' ') }} importé(s) · {{ number_format($lastImport->error_count, 0, ',', ' ') }} erreur(s)</small></div>@else<div class="ud-empty-inline">Aucun import enregistré.</div>@endif
                        <div class="ud-import-actions"><a class="ud-button ud-button-primary" href="{{ route('university.imports') }}">Gérer les imports</a><a href="{{ route('university.imports.template') }}">Télécharger le modèle</a></div>
                    </section>@endif
                </div>

                <div class="ud-secondary-grid">
                    <section class="ud-panel" aria-labelledby="ud-calls-heading"><div class="ud-section-heading"><div><p class="ud-label">OPPORTUNITÉS</p><h2 id="ud-calls-heading">Appels ouverts</h2></div><a href="{{ route('calls.index') }}">Tous les appels</a></div>@forelse($openCalls as $call)<article class="ud-compact-row"><div><strong>{{ $call->title }}</strong><span>Clôture le {{ \Carbon\Carbon::parse($call->closes_at)->format('d/m/Y') }}</span></div></article>@empty<div class="ud-empty-inline">Aucun appel ouvert actuellement.</div>@endforelse</section>

                    <section class="ud-panel" id="ud-messages" aria-labelledby="ud-messages-heading"><div class="ud-section-heading"><div><p class="ud-label">ÉCHANGES</p><h2 id="ud-messages-heading">Messages reçus</h2></div>@if(auth()->user()->can('university.messages.create'))<a href="{{ route('university.messages.index') }}">Messagerie</a>@endif</div>@forelse($recentMessages as $message)<article class="ud-compact-row"><div><strong>{{ $message->sender_name }}</strong><span>Nouveau message reçu</span><time datetime="{{ \Carbon\Carbon::parse($message->created_at)->toIso8601String() }}">{{ \Carbon\Carbon::parse($message->created_at)->format('d/m/Y à H:i') }}</time></div></article>@empty<div class="ud-empty-inline">Aucun nouveau message reçu.</div>@endforelse</section>

                    <section class="ud-panel" id="ud-notifications" aria-labelledby="ud-notifications-heading"><div class="ud-section-heading"><div><p class="ud-label">INFORMATIONS</p><h2 id="ud-notifications-heading">Notifications</h2></div></div>@forelse($notifications as $notification)@php($notificationData = json_decode($notification->data, true) ?: [])<article class="ud-compact-row"><div><strong>{{ $notificationData['subject'] ?? ucfirst(str_replace('.', ' ', $notification->type)) }}</strong><span>{{ $notificationData['message'] ?? 'Une information est disponible dans votre espace.' }}</span><time datetime="{{ \Carbon\Carbon::parse($notification->created_at)->toIso8601String() }}">{{ \Carbon\Carbon::parse($notification->created_at)->format('d/m/Y à H:i') }}</time></div></article>@empty<div class="ud-empty-inline">Aucune notification récente.</div>@endforelse</section>
                </div>

                <section class="ud-foot-summary" aria-label="Autres indicateurs"><span><strong>{{ number_format($incompleteApplications, 0, ',', ' ') }}</strong> dossiers incomplets ou en correction</span><span><strong>{{ number_format($rejectedApplications, 0, ',', ' ') }}</strong> dossiers rejetés</span><span><strong>{{ number_format($researchersCount, 0, ',', ' ') }}</strong> chercheurs rattachés</span><span><strong>{{ number_format($researchProjectsCount, 0, ',', ' ') }}</strong> projets dans les laboratoires</span></section>
                <footer class="ud-footer"><span>© {{ date('Y') }} FOSER · Espace établissement</span><a href="{{ route('university.profile') }}">Profil de l’établissement</a><a href="{{ route('contact') }}">Contacter le FOSER</a></footer>
            </main>
        </div>
    </div>
</body>
</html>