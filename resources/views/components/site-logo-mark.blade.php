@if (!empty($siteLogo))
    <img class="brand-logo" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($siteLogo) }}" alt="FOSER">
@endif