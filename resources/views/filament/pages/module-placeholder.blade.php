<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($this->getModuleItems() as $item)
            @php($url = $this->getModuleItemUrl($item))
            @if ($url)
                <a href="{{ $url }}" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-500 hover:shadow-md dark:border-white/10 dark:bg-gray-900">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ $item }}</h2>
                    <p class="mt-2 text-sm text-primary-600 dark:text-primary-400">Ouvrir le module</p>
                </a>
            @else
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ $item }}</h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Module indisponible</p>
                </div>
            @endif
        @endforeach
    </div>
</x-filament-panels::page>
