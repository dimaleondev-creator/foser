@extends('researcher.layout')
@section('content')
<div class="student-welcome"><div><p class="eyebrow">SUIVI DU PROJET · {{ $project->reference }}</p><h1>{{ $project->title }}</h1><p>État actuel : {{ ucfirst($project->status) }}</p></div></div>
<section class="student-panel"><h2>Financement</h2>@forelse($disbursements as $disbursement)<div class="application-item"><div><strong>{{ $disbursement->reference }}</strong><small>{{ $disbursement->scheduled_for ?: 'Date non planifiée' }}</small></div><span>{{ number_format($disbursement->amount, 0, ',', ' ') }} · {{ $disbursement->status }}</span></div>@empty<p>Aucun décaissement enregistré.</p>@endforelse</section>
<section class="student-panel"><h2>Livrables et documents</h2>@forelse($documents as $document)<div class="application-item"><div><strong>{{ $document->title }}</strong><small>{{ $document->document_role ?: 'Document projet' }}</small></div><span>{{ $document->mime_type }}</span></div>@empty<p>Aucun livrable déposé.</p>@endforelse</section>
@endsection
