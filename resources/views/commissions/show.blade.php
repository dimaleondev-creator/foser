<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $commission->name }} | FOSER</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="student-body"><main class="shell student-main">
    <a class="text-link" href="{{ route('commissions.index') }}">← Mes commissions</a>
    @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="form-errors">{{ $errors->first() }}</div>@endif
    <header class="student-welcome" style="margin-top:32px"><div><p class="eyebrow">{{ $commission->program->name }} · {{ $commission->call->title }}</p><h1>{{ $commission->name }}</h1><p>{{ $commission->description }}</p></div><span class="button button-lime">{{ $commission->status }}</span></header>

    <div class="student-grid">
        <section class="student-panel"><h2>Séance</h2><dl><dt>Date et heure</dt><dd>{{ $commission->scheduled_at->format('d/m/Y H:i') }}</dd><dt>Lieu</dt><dd>{{ $commission->venue ?: 'À préciser' }}</dd><dt>Statut</dt><dd>{{ $commission->status }}</dd><dt>Convocation</dt><dd>{{ $commission->convocation_sent_at?->format('d/m/Y H:i') ?: 'Non émise' }}</dd></dl>
            <h3>Ordre du jour</h3><p>{{ $commission->agenda }}</p>@if($commission->convocation_text)<h3>Convocation</h3><p>{{ $commission->convocation_text }}</p>@endif
        </section>
        <section class="student-panel"><h2>Quorum</h2><strong>{{ $quorum['present'] }} / {{ $quorum['members'] }} présents</strong><p>{{ $quorum['percentage'] }} % · minimum requis {{ $commission->quorum_percentage }} % ({{ $quorum['required'] }} membres)</p><p class="{{ $quorum['reached'] ? 'text-success' : 'text-danger' }}">{{ $quorum['reached'] ? 'Quorum atteint' : 'Quorum non atteint' }}</p></section>
    </div>

    <section class="student-panel" style="margin-top:20px"><div class="panel-title"><h2>Membres et présences</h2><span>{{ $members->count() }} membres</span></div>
        @forelse($members as $member)<article class="application-item"><div><strong>{{ $member->user->name }}</strong><small>{{ ['president' => 'Président', 'secretary' => 'Secrétaire', 'rapporteur' => 'Rapporteur', 'member' => 'Membre'][$member->role] ?? $member->role }} · {{ $member->attendance_status }}</small></div></article>@empty<div class="student-empty">Aucun membre n’est inscrit.</div>@endforelse
    </section>

    <section class="student-panel" style="margin-top:20px"><div class="panel-title"><h2>Dossiers inscrits</h2><span>{{ $items->count() }} candidatures</span></div>
        @forelse($items as $item)
            <article class="commission-case">
                <div class="commission-case-heading"><div><small>{{ $item->application->reference }} · {{ $item->application->program->name }}</small><h3>{{ $item->application->project_title ?: 'Candidature' }}</h3><p>{{ $item->application->applicant->name }} · {{ $item->university_name ?: 'Université non renseignée' }}</p></div><span>{{ $item->application->workflow_status }}</span></div>
                <p><strong>Résumé :</strong> {{ $item->application->summary ?: 'Non renseigné' }}</p>
                <div class="commission-subsection"><h4>Évaluations</h4>
                    @forelse($item->evaluations as $evaluation)<div class="application-item"><div><strong>{{ $evaluation->evaluator_name }}</strong><small>{{ $evaluation->status }}@if($evaluation->comment) · {{ $evaluation->comment }}@endif</small>@foreach($evaluation->scores as $score)<span>{{ $score->name }} : {{ $score->score }} / {{ $score->maximum_score }}@if($score->comment) · {{ $score->comment }}@endif</span>@endforeach</div></div>@empty<p>Aucune évaluation disponible.</p>@endforelse
                </div>
                <div class="commission-subsection"><h4>Pièces justificatives</h4>
                    @if($item->required_documents->isNotEmpty())<p><strong>Pièces obligatoires :</strong> @foreach($item->required_documents as $required){{ $required->label }}@if(!$loop->last), @endif @endforeach</p>@endif
                    @if($item->missing_documents->isNotEmpty())<p class="commission-missing-documents"><strong>Pièces manquantes :</strong> @foreach($item->missing_documents as $missing){{ $missing->label }}@if(!$loop->last), @endif @endforeach</p>@endif
                    @forelse($item->documents as $document)<p><a href="{{ route('commissions.documents.download', [$commission, $item->application_id, $document->id]) }}">{{ $document->title }} · {{ $document->document_type }} · {{ $document->status }} <span aria-hidden="true">↓</span></a></p>@empty<p>Aucune pièce jointe.</p>@endforelse
                </div>
                @if($canManage)<p><strong>Observations administratives :</strong> {{ $item->observations ?: 'Aucune' }}</p>@endif
                @if($item->decision)<p><strong>Délibération :</strong> {{ $item->decision->decision }} · {{ $item->decision->status }} · {{ $item->decision->justification }}</p>@endif
                @if($item->vote_tally)<p class="commission-vote-tally"><strong>Votes :</strong> {{ $item->vote_tally['favorable'] ?? 0 }} favorables · {{ $item->vote_tally['defavorable'] ?? 0 }} défavorables · {{ $item->vote_tally['abstention'] ?? 0 }} abstentions</p>@endif
                @if($canProposeDecision && $commission->status === 'in_progress' && ! $item->decision)
                    <form method="POST" action="{{ route('commissions.decisions.store', $commission) }}" class="commission-vote-form">@csrf<input type="hidden" name="commission_application_id" value="{{ $item->id }}"><label>Délibération proposée<select name="decision" required><option value="">Choisir</option><option value="accepted">Accepté</option><option value="rejected">Rejeté</option><option value="adjourned">Ajourné</option><option value="complement_requested">Complément demandé</option></select></label><label>Justification<textarea name="justification" maxlength="10000" required></textarea></label><button class="button button-dark" type="submit">Proposer la décision</button></form>
                @endif
                @if($item->can_vote)
                    <form method="POST" action="{{ route('commissions.votes.store', $commission) }}" class="commission-vote-form">@csrf<input type="hidden" name="commission_application_id" value="{{ $item->id }}"><label>Votre vote<select name="vote" required><option value="">Choisir</option><option value="favorable">Favorable</option><option value="defavorable">Défavorable</option><option value="abstention">Abstention</option></select></label><label>Observation<textarea name="comment" maxlength="3000"></textarea></label><button class="button button-dark" type="submit">Enregistrer mon vote</button></form>
                @elseif($item->my_vote)<p class="notice">Votre vote est enregistré : {{ $item->my_vote }}. Il est définitif.</p>@endif
            </article>
        @empty<div class="student-empty">Aucun dossier n’a été inscrit à cette commission.</div>@endforelse
    </section>

    <section class="student-panel" style="margin-top:20px"><h2>Procès-verbal</h2>
        @if($canManageMinutes && $commission->status === 'in_progress' && (!$minutes || $minutes->status === 'draft'))<form method="POST" action="{{ route('commissions.minutes.store', $commission) }}" enctype="multipart/form-data" class="commission-vote-form">@csrf<label>Résumé<input name="summary" maxlength="1000" required value="{{ old('summary', $minutes?->summary) }}"></label><label>Compte rendu<textarea name="content" maxlength="30000" required>{{ old('content', $minutes?->content) }}</textarea></label><label>PDF signé (facultatif)<input type="file" name="pdf" accept="application/pdf"></label><button class="button button-dark" type="submit">Enregistrer le procès-verbal</button></form>@endif
        @if($minutes)<p><strong>{{ $minutes->summary }}</strong></p><div class="commission-minutes">{!! nl2br(e($minutes->content)) !!}</div><p>Rédigé par {{ $minutes->author->name }} · {{ $minutes->created_at?->format('d/m/Y H:i') }} · {{ $minutes->status }}</p>@if($minutes->status === 'validated' && $minutes->document)<a class="button button-dark" href="{{ route('commissions.minutes.download', $commission) }}">Télécharger le PDF <span aria-hidden="true">↓</span></a>@endif
        @else<div class="student-empty">Le procès-verbal validé sera consultable ici.</div>@endif
    </section>

    @if($history->isNotEmpty())<section class="student-panel" style="margin-top:20px"><h2>Historique de la commission</h2><ol>@foreach($history as $event)<li><strong>{{ $event->event }}</strong> · {{ \Carbon\Carbon::parse($event->created_at)->format('d/m/Y H:i') }}</li>@endforeach</ol></section>@endif
</main></body></html>