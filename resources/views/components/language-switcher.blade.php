<div class="language-switcher" aria-label="{{ __('messages.language') }}">
    <span>{{ __('messages.language') }}:</span>
    @foreach (config('app.supported_locales') as $locale)
        @if (app()->getLocale() === $locale)
            <strong>{{ strtoupper($locale) }}</strong>
        @else
            <a href="{{ route('language.switch', $locale) }}">{{ strtoupper($locale) }}</a>
        @endif
    @endforeach
</div>
