@php($hasChildren = $unit->children->isNotEmpty())
<article class="organization-node {{ $hasChildren ? 'has-children' : '' }}" data-organization-item>
    <div class="organization-card">
        @if ($unit->photo_url)<img src="{{ $unit->photo_url }}" alt="{{ $unit->responsible?->name ?? $unit->localized_name }}">@endif
        <div class="organization-copy"><h2>@if($unit->unit_type === 'direction')<a href="{{ route('institution.direction', $unit) }}">{{ $unit->localized_name }}</a>@else{{ $unit->localized_name }}@endif</h2>@if ($unit->localized_function)<p>{{ $unit->localized_function }}</p>@endif @if ($unit->localized_biography)<div class="organization-bio">{{ $unit->localized_biography }}</div>@endif @if ($unit->responsible)<small>{{ $unit->responsible->name }}</small>@endif</div>
        @if ($hasChildren)<button type="button" data-organization-toggle aria-expanded="false" aria-label="{{ app()->getLocale() === 'en' ? 'Show children' : 'Afficher les niveaux inférieurs' }}">+</button>@endif
    </div>
    @if ($hasChildren)<div class="organization-children">@foreach ($unit->children as $child) @include('public.partials.organization-node', ['unit' => $child]) @endforeach</div>@endif
</article>
