@extends('researcher.layout')
@section('content')
@if($laboratory)
<div class="student-welcome"><div><p class="eyebrow">LABORATOIRE</p><h1>{{ $laboratory->name }}</h1><p>{{ $laboratory->university_name ?: 'Établissement non renseigné' }}</p></div></div>
@else
<div class="student-welcome"><div><p class="eyebrow">LABORATOIRE</p><h1>Aucun laboratoire associé</h1><p>Votre profil chercheur n’est pas encore rattaché à un laboratoire.</p></div><a class="button button-lime" href="{{ route('researcher.profile') }}">Compléter mon profil</a></div>
<section class="student-panel"><p class="student-empty">Renseignez ou demandez l’association de votre laboratoire dans votre profil. Les informations du laboratoire apparaîtront ici une fois le rattachement enregistré.</p></section>
@endif
@if($laboratory)
<section class="student-panel"><h2>Présentation</h2><p>{{ $laboratory->description ?: 'Aucune description disponible.' }}</p><h2>Membres</h2>@forelse($members as $member)<div class="application-item"><div><strong>{{ $member->name }}</strong><small>{{ $member->academic_rank ?: 'Grade non renseigné' }} · {{ $member->research_domain ?: 'Domaine non renseigné' }}</small></div></div>@empty<p class="student-empty">Aucun membre renseigné.</p>@endforelse</section>
<section class="student-panel"><h2>Projets du laboratoire</h2>@forelse($projects as $project)<div class="application-item"><div><strong>{{ $project->title }}</strong><small>{{ $project->reference }}</small></div><span>{{ ucfirst($project->status) }}</span></div>@empty<p class="student-empty">Aucun projet renseigné.</p>@endforelse</section>
@endif
@endsection
