<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Centre documentaire | FOSER</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="shell section-pad">
    <a href="{{ route('home') }}">← Accueil</a>
    <p class="eyebrow">RESSOURCES OFFICIELLES</p>
    <h1>Centre documentaire</h1>

    <form method="GET" action="{{ route('documents.index') }}" class="student-panel form-row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin:24px 0">
        <label>Rechercher
            <input name="q" value="{{ request('q') }}" placeholder="Titre, description, catégorie, mots-clés">
        </label>
        <label>Catégorie
            <select name="category">
                <option value="">Toutes</option>
                @foreach($categories as $category)
                    <option value="{{ $category->slug }}" @selected(request('category') === $category->slug)>{{ $category->name }}</option>
                @endforeach
            </select>
        </label>
        <label>Type
            <select name="type">
                <option value="">Tous</option>
                @foreach($types as $type)
                    <option value="{{ $type }}" @selected(request('type') === $type)>{{ ucfirst($type) }}</option>
                @endforeach
            </select>
        </label>
        <label>Année
            <input name="year" type="number" min="1900" max="2100" value="{{ request('year') }}">
        </label>
        <label>Langue
            <select name="language">
                <option value="">Toutes</option>
                <option value="fr" @selected(request('language') === 'fr')>Français</option>
                <option value="en" @selected(request('language') === 'en')>Anglais</option>
                <option value="pt" @selected(request('language') === 'pt')>Portugais</option>
                <option value="ar" @selected(request('language') === 'ar')>Arabe</option>
            </select>
        </label>
        <button class="button button-dark" type="submit">Filtrer</button>
    </form>

    <nav aria-label="Catégories documentaires" class="form-row" style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:24px">
        <a href="{{ route('documents.index') }}">Toutes les catégories</a>
        @foreach($categories as $category)
            <a href="{{ route('documents.category', $category->slug) }}">{{ $category->name }}</a>
        @endforeach
    </nav>

    <div class="news-grid">
        @forelse($documents as $document)
            <article class="student-panel">
                <p class="eyebrow">
                    {{ $document->category?->name ?: ucfirst($document->document_type) }}
                    @if($document->year) · {{ $document->year }} @endif
                    · {{ strtoupper($document->language) }}
                </p>
                <h2>{{ $document->title }}</h2>
                @if($document->description)<p>{{ $document->description }}</p>@endif
                <p>{{ $document->author ?: 'FOSER' }}
                    @if($document->reference) · Réf. {{ $document->reference }} @endif
                    @if($document->version_label) · Version {{ $document->version_label }} @endif
                </p>
                @if($document->mime_type === 'application/pdf')
                    <a class="button button-dark" href="{{ route('documents.preview', $document) }}" target="_blank" rel="noopener">Prévisualiser le PDF</a>
                @endif
                <a class="button button-lime" href="{{ route('documents.download', $document) }}">Télécharger</a>
                <p class="text-sm">{{ number_format(($document->size ?? 0) / 1048576, 2) }} Mo · {{ $document->downloads_count }} téléchargement(s)</p>
            </article>
        @empty
            <p>Aucun document ne correspond à votre recherche.</p>
        @endforelse
    </div>
    {{ $documents->links() }}
</main>
</body>
</html>