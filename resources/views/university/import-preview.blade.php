@extends('university.layout')
@section('content')
<div class="student-welcome"><div><p class="eyebrow">IMPORT EXCEL</p><h1>Prévisualisation</h1><p>{{ $filename }}</p></div></div>
<section class="student-panel"><p>Les dix premières lignes sont affichées. Aucune donnée n’a encore été enregistrée.</p>
<div class="table-wrap"><table><thead><tr>@foreach(array_keys($rows[0] ?? []) as $header)<th>{{ $header }}</th>@endforeach</tr></thead><tbody>@forelse($rows as $row)<tr>@foreach($row as $value)<td>{{ $value }}</td>@endforeach</tr>@empty<tr><td>Aucune ligne de données.</td></tr>@endforelse</tbody></table></div>
<a class="button button-dark" href="{{ route('university.dashboard') }}">Retour au tableau de bord</a></section>
@endsection
