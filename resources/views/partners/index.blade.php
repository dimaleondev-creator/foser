<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="description" content="Découvrez les partenaires institutionnels du FOSER."><title>Partenaires | FOSER</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="partner-page">
	<header class="site-header partner-header">
		<div class="shell nav-wrap">
			<a class="brand brand-logo-only" href="{{ route('home') }}" aria-label="FOSER, accueil"><x-site-logo-mark /><span class="brand-tagline">Partenaires institutionnels</span></a>
			<nav class="partner-nav" aria-label="Navigation principale">
				<a href="{{ route('home') }}">Accueil</a>
				<a href="{{ route('partners.index') }}" aria-current="page">Partenaires</a>
				@if(auth()->user()?->account_type === 'directeur_general')
					<a class="partner-nav-director" href="{{ route('directeur_general.dashboard') }}">Tableau de direction <span aria-hidden="true">↗</span></a>
				@endif
			</nav>
		</div>
	</header>

	<main>
		<section class="partner-hero">
			<div class="shell partner-hero-inner">
				<div>
					<p class="partner-eyebrow">Un réseau engagé</p>
					<h1>Des alliances pour faire grandir les possibles.</h1>
					<p>Les institutions et organisations qui accompagnent les ambitions éducatives et scientifiques du FOSER.</p>
				</div>
				<div class="partner-count" aria-label="{{ $partners->total() }} partenaires affichés">
					<strong>{{ str_pad((string) $partners->total(), 2, '0', STR_PAD_LEFT) }}</strong>
					<span>partenaires<br>dans le réseau</span>
				</div>
			</div>
		</section>

		<section class="shell partner-directory" aria-labelledby="partner-list-title">
			<div class="partner-directory-heading">
				<div><p class="section-kicker"><span>01</span><span>Le réseau FOSER</span></p><h2 id="partner-list-title">Nos partenaires</h2></div>
				<p class="partner-directory-note">Des engagements concrets, au service de l’éducation, de la recherche et de l’innovation.</p>
			</div>
			<form method="GET" class="partner-search" role="search">
				<label for="partner-query">Explorer le réseau</label>
				<div class="partner-search-controls"><input id="partner-query" name="q" value="{{ request('q') }}" placeholder="Nom, catégorie ou description"><button class="button button-dark" type="submit">Rechercher <span aria-hidden="true">→</span></button></div>
			</form>
			<section class="partner-grid" aria-label="Liste des partenaires">
				@forelse($partners as $partner)
					<article class="partner-card">
						<div class="partner-card-top">
							<div class="partner-logo">@if($partner->logo_path)<img src="{{ asset('storage/'.$partner->logo_path) }}" alt="Logo {{ $partner->name }}" loading="lazy">@else<span aria-hidden="true">{{ mb_substr($partner->name, 0, 1) }}</span>@endif</div>
							<span class="partner-category">{{ $partner->category ?: ($partner->type ?: 'Partenaire') }}</span>
						</div>
						<h3>{{ $partner->name }}</h3>
						<p>{{ $partner->description ?: 'Un partenaire engagé aux côtés du FOSER.' }}</p>
						<a class="partner-discover" href="{{ route('partners.show', $partner) }}">Découvrir <span aria-hidden="true">↗</span></a>
					</article>
				@empty
					<p class="empty-state partner-empty">Aucun partenaire publié pour cette recherche.</p>
				@endforelse
			</section>
			@if($partners->hasPages())<nav class="partner-pagination" aria-label="Pagination des partenaires">{{ $partners->links() }}</nav>@endif
		</section>
	</main>

	<footer class="footer partner-footer">
		<div class="shell partner-footer-main">
			<a class="brand brand-light brand-logo-only" href="{{ route('home') }}" aria-label="FOSER, accueil"><x-site-logo-mark /><span class="brand-tagline">Fonds de Soutien à l'Éducation<br>et à la Recherche</span></a>
			<p>Des partenariats solides pour ouvrir de nouvelles voies.</p>
			<nav aria-label="Liens de pied de page"><a href="{{ route('home') }}">Accueil</a><a href="{{ route('partners.index') }}">Tous les partenaires</a><a href="{{ route('contact') }}">Nous contacter</a></nav>
		</div>
		<div class="shell footer-bottom"><span>© {{ date('Y') }} FOSER · Tous droits réservés</span><a href="{{ route('home') }}">Burkina Faso</a></div>
	</footer>
</body>
</html>
