<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Dossier {{ $application->reference }} | FOSER</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="student-body"><main class="shell student-main"><a class="text-link" href="{{ route('filament.admin.resources.applications.index') }}">← Retour aux candidatures</a><div class="student-welcome" style="margin-top:42px"><div><p class="eyebrow">DOSSIER {{ $application->reference }}</p><h1>{{ $application->project_title ?: 'Candidature' }}</h1><p>{{ $application->applicant?->name }} · {{ $student?->inee ?: 'INEE non renseigné' }} · {{ $university?->name ?: 'Université non renseignée' }}</p></div><span class="button button-lime">{{ $application->workflow_status ?: $application->status }}</span></div>
@if(session('status'))<div class="notice">{{ session('status') }}</div>@endif@if($errors->any())<div class="notice">{{ $errors->first() }}</div>@endif
<section class="student-panel" style="margin-bottom:20px"><h2>Actions du workflow</h2><div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:18px">
@if($application->workflow_status === 'submitted' && auth()->user()->can('applications.verify'))<form method="POST" action="{{ route('admin.applications.workflow.verify', $application) }}" onsubmit="return confirm('Vérifier la complétude de ce dossier ?')">@csrf<button class="button button-dark">Vérifier la complétude</button></form>@endif
@if($application->workflow_status === 'university_review' && auth()->user()->can('applications.verify'))<form method="POST" action="{{ route('admin.applications.workflow.university.approve', $application) }}" onsubmit="return confirm('Valider la vérification universitaire ?')">@csrf<button class="button button-lime">Valider université</button></form>@endif
@if($application->workflow_status === 'university_review' && auth()->user()->can('applications.reject'))<form method="POST" action="{{ route('admin.applications.workflow.university.reject', $application) }}" onsubmit="return confirm('Rejeter ce dossier lors de la vérification universitaire ?')">@csrf<input name="reason" required placeholder="Motif du rejet"><button class="button button-dark" type="submit">Rejeter</button></form>@endif
@if(in_array($application->workflow_status, ['university_review','evaluation'], true) && auth()->user()->can('applications.assign_evaluator'))<form method="POST" action="{{ route('admin.applications.workflow.assign', $application) }}" style="display:flex;gap:8px;align-items:center">@csrf<select name="evaluator_ids[]" required><option value="">Évaluateur</option>@foreach($evaluators as $evaluator)<option value="{{ $evaluator->id }}">{{ $evaluator->name }}</option>@endforeach</select><button class="button button-lime" type="submit">Affecter</button></form>@endif
@if($application->workflow_status === 'evaluation' && auth()->user()->can('applications.review_commission'))<form method="POST" action="{{ route('admin.applications.workflow.commission', $application) }}" onsubmit="return confirm('Transmettre ce dossier à la commission ?')">@csrf<button class="button button-dark">Ouvrir commission</button></form>@endif
@if($application->workflow_status === 'commission_review' && auth()->user()->can('applications.decide'))<form method="POST" action="{{ route('admin.applications.workflow.decision', $application) }}" style="display:flex;gap:8px;flex-wrap:wrap">@csrf<select name="decision" required><option value="">Décision</option><option value="accepted">Accepter</option><option value="rejected">Rejeter</option><option value="waitlisted">Liste d’attente</option><option value="deferred">Différer</option></select><input name="amount" type="number" min="0" step="0.01" placeholder="Montant"><input name="reason" placeholder="Motif"><button class="button button-lime" type="submit">Enregistrer décision</button></form>@endif
@if($application->workflow_status === 'decision_made' && auth()->user()->can('applications.publish_result'))<form method="POST" action="{{ route('admin.applications.workflow.publish', $application) }}" onsubmit="return confirm('Publier le résultat au candidat ?')">@csrf<button class="button button-lime">Publier résultat</button></form>@endif
@if($application->workflow_status === 'result_published' && auth()->user()->can('applications.award'))<form method="POST" action="{{ route('admin.applications.workflow.award', $application) }}">@csrf<input name="amount" type="number" min="0" step="0.01" required placeholder="Montant attribué"><button class="button button-lime">Attribuer l’aide</button></form>@endif
@if($application->workflow_status === 'awarded' && auth()->user()->can('applications.finance'))<form method="POST" action="{{ route('admin.applications.workflow.commit', $application) }}">@csrf<input name="amount" type="number" min="0" step="0.01" required placeholder="Montant engagé"><input name="currency" value="FCFA" maxlength="4" required><button class="button button-dark">Engager</button></form>@endif
@if($application->workflow_status === 'committed' && auth()->user()->can('applications.disburse'))<form method="POST" action="{{ route('admin.applications.workflow.disburse', $application) }}">@csrf<input name="amount" type="number" min="0" step="0.01" required placeholder="Montant décaissé"><input name="payment_reference" required placeholder="Référence paiement"><button class="button button-lime">Décaisser</button></form>@endif
</div></section>
<div class="student-grid"><section class="student-panel"><h2>Informations</h2><dl><dt>Programme</dt><dd>{{ $application->program?->name ?: 'Non renseigné' }}</dd><dt>Appel</dt><dd>{{ $call?->title ?: 'Non renseigné' }}</dd><dt>Créé le</dt><dd>{{ $application->created_at?->format('d/m/Y H:i') }}</dd><dt>Soumis le</dt><dd>{{ $application->submitted_at?->format('d/m/Y H:i') ?: 'Non soumis' }}</dd><dt>Domaine</dt><dd>{{ $application->domain ?: 'Non renseigné' }}</dd></dl></section><section class="student-panel"><h2>Finance</h2><p>Montant demandé : {{ $application->budget ?: 'Non renseigné' }}</p><p>Décision : {{ $result?->decision ?: 'Non renseignée' }}</p><p>Attribution : {{ $award?->amount ?: 'Non renseignée' }}</p><p>Engagement : {{ $commitment?->amount ?: 'Non renseigné' }}</p><p>Décaissement : {{ $disbursement?->amount ?: 'Non renseigné' }}</p></section></div>
<section class="student-panel" style="margin-top:20px"><h2>Pièces</h2>@forelse($documents as $document)<p>{{ $document->document_type ?: 'Document' }} · {{ $document->status }}</p>@empty<p>Aucune pièce enregistrée.</p>@endforelse</section>
<section class="student-panel" style="margin-top:20px"><h2>Évaluations</h2>
@forelse($evaluations as $evaluation)
<p>{{ $evaluation->evaluator_name }} · {{ $evaluation->status }}
@if($evaluation->conflict_declared) · conflit déclaré @endif
@if($evaluation->assigned_at) · attribuée {{ \Carbon\Carbon::parse($evaluation->assigned_at)->format('d/m/Y H:i') }} @endif
@if($evaluation->submitted_at) · soumise {{ \Carbon\Carbon::parse($evaluation->submitted_at)->format('d/m/Y H:i') }} @endif
@if($evaluation->validated_at) · validée {{ \Carbon\Carbon::parse($evaluation->validated_at)->format('d/m/Y H:i') }} @endif
</p>
@foreach($evaluation->scores as $score)
<p><strong>{{ $score->criterion_name }} :</strong> {{ $score->score }} / {{ $score->maximum_score }} @if($score->comment)· {{ $score->comment }}@endif</p>
@endforeach
@if($evaluation->status === 'submitted' && auth()->user()->can('evaluations.validate'))
<form method="POST" action="{{ route('admin.evaluations.validate', $evaluation->id) }}" onsubmit="return confirm('Valider l’évaluation ?')">
@csrf
<button class="button button-lime">Valider l’évaluation</button>
</form>
@elseif(in_array($evaluation->status, ['assigned', 'in_progress', 'conflict'], true) && auth()->user()->can('applications.assign_evaluator'))
<form method="POST" action="{{ route('admin.applications.evaluators.reassign', [$application->id, $evaluation->id]) }}" onsubmit="return confirm('Réattribuer ?')">
@csrf @method('PUT')
<select name="evaluator_id" required>
<option value="">Choisir un nouvel évaluateur</option>
@foreach($evaluators as $evaluator)
@if($evaluator->id !== $evaluation->evaluator_id)
<option value="{{ $evaluator->id }}">{{ $evaluator->name }} {{ implode(', ', $evaluator->evaluation_expertise) }}</option>
@endif
@endforeach
</select>
<button class="button button-lime">Réattribuer</button>
</form>
@endif
@empty
<p>Aucun évaluateur affecté.</p>
@endforelse
</section>
<section class="student-panel" style="margin-top:20px"><h2>Historique</h2><ol>@forelse($history as $event)<li><strong>{{ $event->to_status }}</strong> · {{ \Carbon\Carbon::parse($event->changed_at)->format('d/m/Y H:i') }}@if($event->reason) · {{ $event->reason }}@endif</li>@empty<li>Dossier créé, historique à compléter.</li>@endforelse</ol></section></main></body></html>
