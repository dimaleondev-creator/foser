<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Direction FOSER | Tableau décisionnel</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'))) @vite(['resources/css/app.css', 'resources/js/app.js']) @endif
    <style>
        .director-dashboard { --ink:#152b35; --muted:#61747b; --line:#d8e1df; --paper:#f3f7f5; --teal:#126a67; --lime:#d7ed8a; color:var(--ink); }
        .director-dashboard .masthead { border-bottom:1px solid var(--line); background:#fff; }
        .director-dashboard .masthead-inner { display:flex; align-items:center; justify-content:space-between; gap:20px; min-height:72px; }
        .director-dashboard .brand-name { display:flex; align-items:center; gap:12px; font-weight:700; }
        .director-dashboard .brand-name small { display:block; color:var(--muted); font-weight:500; }
        .director-dashboard .toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
        .director-dashboard .toolbar a, .director-dashboard .toolbar button { text-decoration:none; }
        .director-dashboard .intro { padding:28px 0 22px; display:flex; align-items:flex-end; justify-content:space-between; gap:20px; }
        .director-dashboard h1 { margin:4px 0; font-size:30px; line-height:1.15; }
        .director-dashboard h2 { margin:0 0 16px; font-size:18px; }
        .director-dashboard .eyebrow { margin:0; color:var(--teal); font-size:12px; font-weight:700; }
        .director-dashboard .subtle { color:var(--muted); }
        .director-dashboard .section { padding:20px 0; border-top:1px solid var(--line); }
        .director-dashboard .section-heading { display:flex; align-items:baseline; justify-content:space-between; gap:12px; margin-bottom:14px; }
        .director-dashboard .kpi-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; }
        .director-dashboard .kpi { min-height:102px; padding:14px 16px; background:#fff; border:1px solid var(--line); border-top:3px solid var(--teal); }
        .director-dashboard .kpi strong { display:block; margin-top:10px; font-size:25px; line-height:1; font-variant-numeric:tabular-nums; }
        .director-dashboard .kpi span { color:var(--muted); font-size:13px; }
        .director-dashboard .filter-form { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)) auto auto; gap:10px; align-items:end; padding:16px; background:#fff; border:1px solid var(--line); }
        .director-dashboard .filter-form label { display:block; font-size:12px; color:var(--muted); }
        .director-dashboard .filter-form input, .director-dashboard .filter-form select { width:100%; min-height:40px; margin-top:5px; padding:7px 9px; border:1px solid var(--line); background:#fff; color:var(--ink); }
        .director-dashboard .chart-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
        .director-dashboard .chart-panel { padding:16px; background:#fff; border:1px solid var(--line); min-width:0; }
        .director-dashboard .bar-list { display:grid; gap:9px; }
        .director-dashboard .bar-row { display:grid; grid-template-columns:minmax(95px,1.2fr) minmax(80px,2fr) auto; align-items:center; gap:9px; font-size:12px; }
        .director-dashboard .bar-label { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .director-dashboard .bar-track { height:9px; background:#e5eeeb; }
        .director-dashboard .bar-fill { display:block; width:100%; height:9px; border:0; background:#e5eeeb; color:var(--teal); }
        .director-dashboard .bar-fill::-webkit-progress-bar { background:#e5eeeb; }
        .director-dashboard .bar-fill::-webkit-progress-value { background:var(--teal); }
        .director-dashboard .bar-fill::-moz-progress-bar { background:var(--teal); }
        .director-dashboard .bar-value { min-width:48px; text-align:right; font-variant-numeric:tabular-nums; }
        .director-dashboard .alert-grid { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:10px; }
        .director-dashboard .alert { padding:14px; border:1px solid var(--line); background:#fff; }
        .director-dashboard .alert[data-active="true"] { border-left:4px solid #bb623d; }
        .director-dashboard .alert strong { display:block; font-size:25px; }
        .director-dashboard .alert span { color:var(--muted); font-size:12px; }
        .director-dashboard .button-lime { background:var(--lime); color:var(--ink); }
        @media (max-width:900px) { .director-dashboard .filter-form { grid-template-columns:repeat(3,minmax(0,1fr)); } .director-dashboard .kpi-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .director-dashboard .alert-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width:620px) { .director-dashboard .masthead-inner, .director-dashboard .intro { align-items:flex-start; flex-direction:column; } .director-dashboard .filter-form, .director-dashboard .chart-grid { grid-template-columns:1fr; } .director-dashboard .kpi-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .director-dashboard .toolbar { width:100%; } }
        @media print { .director-dashboard .masthead, .director-dashboard .filter-form, .director-dashboard .toolbar, .director-dashboard .no-print { display:none !important; } .director-dashboard .section { break-inside:avoid; } .director-dashboard .chart-grid { grid-template-columns:repeat(2,1fr); } body { background:#fff !important; } }
    </style>
</head>
<body class="student-body director-dashboard">
<header class="masthead"><div class="shell masthead-inner"><a class="brand brand-name" href="{{ route('directeur_general.dashboard') }}"><x-site-logo-mark /><span><strong>FOSER</strong><small>Direction générale</small></span></a><div class="toolbar"><a class="button button-dark" href="{{ route('partners.index') }}">Partenaires ↗</a><a class="button button-dark" href="{{ route('directeur_general.dashboard.export.xlsx', request()->query()) }}" title="Exporter au format Excel">Excel ↓</a><a class="button button-dark" href="{{ route('directeur_general.dashboard.export.pdf', request()->query()) }}" title="Télécharger le rapport PDF">PDF ↓</a><button class="button button-dark" type="button" onclick="window.print()" title="Imprimer le tableau">Imprimer</button><a class="button button-lime" href="{{ route('directeur_general.dashboard', request()->query()) }}" title="Actualiser les indicateurs">Actualiser ↻</a><form method="POST" action="{{ route('auth.logout') }}">@csrf<button class="button button-dark" type="submit">Déconnexion</button></form></div></div></header>
<main class="shell student-main">
    <div class="intro"><div><p class="eyebrow">PILOTAGE INSTITUTIONNEL</p><h1>Tableau décisionnel</h1><p class="subtle">Bonjour {{ $user->name }} · Données actualisées {{ $data['generated_at']->format('d/m/Y à H:i') }}</p></div></div>
    <form class="filter-form" method="GET" action="{{ route('directeur_general.dashboard') }}">
        <label>Du<input type="date" name="from" value="{{ $data['filters']['from'] ?? '' }}"></label>
        <label>Au<input type="date" name="to" value="{{ $data['filters']['to'] ?? '' }}"></label>
        <label>Année<select name="year"><option value="">Toutes</option>@foreach($data['options']['years'] as $year)<option value="{{ $year }}" @selected((string) ($data['filters']['year'] ?? '') === (string) $year)>{{ $year }}</option>@endforeach</select></label>
        <label>Année académique<select name="academic_year"><option value="">Toutes</option>@foreach($data['options']['academic_years'] as $year)<option value="{{ $year }}" @selected(($data['filters']['academic_year'] ?? '') === $year)>{{ $year }}</option>@endforeach</select></label>
        <label>Région<select name="region"><option value="">Toutes</option>@foreach($data['options']['regions'] as $region)<option value="{{ $region }}" @selected(($data['filters']['region'] ?? '') === $region)>{{ $region }}</option>@endforeach</select></label>
        <label>Université<select name="university_id"><option value="">Toutes</option>@foreach($data['options']['universities'] as $university)<option value="{{ $university->id }}" @selected(($data['filters']['university_id'] ?? '') === $university->id)>{{ $university->name }}</option>@endforeach</select></label>
        <label>Programme<select name="program_id"><option value="">Tous</option>@foreach($data['options']['programs'] as $program)<option value="{{ $program->id }}" @selected(($data['filters']['program_id'] ?? '') === $program->id)>{{ $program->name }}</option>@endforeach</select></label>
        <label>Sexe<select name="sex"><option value="">Tous</option>@foreach($data['options']['sexes'] as $sex)<option value="{{ $sex }}" @selected(($data['filters']['sex'] ?? '') === $sex)>{{ $sex }}</option>@endforeach</select></label>
        <label>Type de bénéficiaire<select name="beneficiary_type"><option value="">Tous</option>@foreach($data['options']['beneficiary_types'] as $key => $label)<option value="{{ $key }}" @selected(($data['filters']['beneficiary_type'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></label>
        <label>Statut<select name="status"><option value="">Tous</option>@foreach($data['options']['statuses'] as $key => $label)<option value="{{ $key }}" @selected(($data['filters']['status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></label>
        <button class="button button-lime" type="submit">Appliquer</button><a class="button button-dark" href="{{ route('directeur_general.dashboard') }}">Effacer</a>
    </form>
    @if($errors->any())<div class="form-errors">{{ $errors->first() }}</div>@endif

    <section class="section"><div class="section-heading"><h2>Activité globale</h2><span class="subtle">Volumes sur la période sélectionnée</span></div><div class="kpi-grid">
        @foreach([['Étudiants inscrits','students'],['Chercheurs inscrits','researchers'],['Universités','universities'],['Candidatures / dossiers déposés','applications'],['En attente','applications_pending'],['En traitement','applications_processing'],['Validés','applications_validated'],['Rejetés','applications_rejected'],['Taux de traitement','treatment_rate']] as [$label,$key])
            <article class="kpi"><span>{{ $label }}</span><strong>{{ number_format($data['summary'][$key], 0, ',', ' ') }}{{ $key === 'treatment_rate' ? ' %' : '' }}</strong></article>
        @endforeach
    </div></section>

    <section class="section"><div class="section-heading"><h2>Situation financière</h2><span class="subtle">Montants issus des engagements, attributions et paiements enregistrés</span></div><div class="kpi-grid">
        @foreach([['Montant demandé','amount_requested'],['Montant engagé','amount_committed'],['Montant attribué','amount_awarded'],['Montant décaissé','amount_disbursed'],['Montant payé','amount_paid'],['Montant restant','amount_remaining'],['Nombre de paiements','payments'],['Nombre de décaissements','disbursements'],['Paiements en attente','payments_pending'],['Paiements échoués','payments_failed'],['Paiements terminés','payments_completed']] as [$label,$key])
            <article class="kpi"><span>{{ $label }}@if(str_starts_with($key, 'amount_')) · FCFA @endif</span><strong>{{ number_format($data['summary'][$key], str_starts_with($key, 'amount_') ? 0 : 0, ',', ' ') }}</strong></article>
        @endforeach
    </div></section>

    <section class="section"><div class="section-heading"><h2>Recherche</h2><span class="subtle">Projets de recherche rattachés aux chercheurs et engagements</span></div><div class="kpi-grid">
        @foreach([['Projets soumis','projects_submitted'],['En évaluation','projects_evaluation'],['Financés','projects_funded'],['Terminés','projects_completed'],['Montant consacré','research_amount']] as [$label,$key])
            <article class="kpi"><span>{{ $label }}@if($key === 'research_amount') · FCFA @endif</span><strong>{{ number_format($data['summary'][$key], 0, ',', ' ') }}</strong></article>
        @endforeach
    </div></section>

    <section class="section"><div class="section-heading"><h2>Analyses</h2><span class="subtle">Répartition des données filtrées · le sexe est disponible pour les profils étudiants</span></div><div class="chart-grid">
        @foreach([['Évolution des candidatures','monthly_applications',false],['Dossiers par statut','by_status',false],['Dossiers par région · effectifs réels','by_region',false],['Dossiers par université','by_university',false],['Répartition hommes / femmes','by_sex',false],['Répartition par programme','by_program',false],['Montants engagés, décaissés et payés','financial_comparison',true],['Évolution des décaissements','disbursements',true],['Projets financés par programme','funded_research',false],['Performance de traitement','treatment_performance',false]] as [$title,$key,$money])
            @php($rows = $data['charts'][$key])
            @php($maxValue = max(1, (float) collect($rows)->max('value')))
            <section class="chart-panel"><h2>{{ $title }}</h2><div class="bar-list">
                @forelse($rows as $row)
                    <div class="bar-row"><span class="bar-label" title="{{ $row['label'] }}">{{ $row['label'] }}</span><div class="bar-track" role="img" aria-label="{{ $row['label'] }}: {{ $row['value'] }}"><progress class="bar-fill" max="100" value="{{ min(100, ((float) $row['value'] / $maxValue) * 100) }}" aria-hidden="true"></progress></div><strong class="bar-value">{{ number_format($row['value'], 0, ',', ' ') }}</strong></div>
                @empty<p class="subtle">Aucune donnée pour les filtres sélectionnés.</p>@endforelse
            </div></section>
        @endforeach
    </div></section>

    <section class="section"><div class="section-heading"><h2>Alertes décisionnelles</h2><span class="subtle">Comptages d’éléments nécessitant un suivi, sans données personnelles</span></div><div class="alert-grid">
        @foreach($data['alerts'] as $alert)<article class="alert" data-active="{{ $alert['count'] > 0 ? 'true' : 'false' }}"><strong>{{ number_format($alert['count'], 0, ',', ' ') }}</strong><span>{{ $alert['label'] }}</span></article>@endforeach
    </div></section>
</main>
</body>
</html>
