@if($footerPartners->isNotEmpty())
    <div class="footer-partners">
        <div class="shell">
            <h2>Nos partenaires</h2>
            <div class="footer-partner-list">
                @foreach($footerPartners as $partner)
                    <a class="footer-partner" href="{{ route('partners.show', $partner->slug) }}" aria-label="{{ $partner->name }}">
                        @if($partner->logo_path)
                            <img src="{{ asset('storage/'.$partner->logo_path) }}" alt="Logo {{ $partner->name }}" loading="lazy">
                        @else
                            <span>{{ $partner->name }}</span>
                        @endif
                    </a>
                @endforeach
                <a class="footer-partners-more" href="{{ route('partners.index') }}">Tous les partenaires <span>→</span></a>
            </div>
        </div>
    </div>
@endif