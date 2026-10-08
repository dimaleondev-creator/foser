<x-filament-panels::page>
<div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-gray-900">
    <p class="text-sm text-gray-500">Assistant fondé sur les sources publiques officielles FOSER. Il ne prend aucune décision administrative.</p>
    <form method="POST" action="{{ route('assistant.ask') }}" class="mt-6 space-y-4">@csrf<label class="block text-sm font-medium">Question<textarea name="question" rows="5" maxlength="1000" required class="fi-input mt-1 w-full"></textarea></label><button class="fi-btn fi-btn-color-primary" type="submit">Demander une analyse</button></form>
</div>
</x-filament-panels::page>
