<?php

namespace App\Services;

use App\Contracts\AIProviderInterface;
use App\Models\Call;
use App\Models\Document;
use App\Models\Event;
use App\Models\News;
use App\Models\Partner;
use App\Models\Program;
use App\Models\ProgramFaq;
use Illuminate\Support\Str;

class RagService
{
    public function __construct(private readonly AIProviderInterface $provider) {}

    public function answer(string $question): array
    {
        $normalized = Str::lower(trim($question));
        $tokens = collect(preg_split('/\s+/u', $normalized) ?: [])->filter(fn (string $token): bool => mb_strlen($token) >= 3)->values();
        if ($tokens->isEmpty()) return ['answer' => null, 'sources' => [], 'confidence' => 0, 'refused' => true];

        $sources = collect()
            ->concat($this->records(ProgramFaq::query()->where('status', 'published'), 'question', 'answer', route('faq')))
            ->concat($this->records(News::query()->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now()), 'title', 'body', route('news.index')))
            ->concat($this->records(Program::query()->where('status', 'published'), 'name', 'description', route('programs.index')))
            ->concat($this->records(Call::query()->whereIn('status', ['published', 'suspended', 'closed']), 'title', 'description', route('calls.index')))
            ->concat($this->records(Event::query()->where('status', 'published'), 'title', 'description', route('events.index')))
            ->concat($this->records(Partner::query()->where('status', 'published'), 'name', 'description', route('partners.index')))
            ->map(function (array $source) use ($tokens): array {
                $haystack = Str::lower($source['title'].' '.$source['excerpt']);
                $matches = $tokens->filter(fn (string $token): bool => Str::contains($haystack, $token))->count();
                $source['score'] = $matches / max($tokens->count(), 1);
                return $source;
            })->filter(fn (array $source): bool => $source['score'] > 0)->sortByDesc('score')->take(3)->values();

        $confidence = round((float) ($sources->first()['score'] ?? 0), 2);
        if ($confidence < 0.34) return ['answer' => null, 'sources' => [], 'confidence' => $confidence, 'refused' => true];

        $sourceData = $sources->map(fn (array $source): array => ['title' => $source['title'], 'excerpt' => Str::limit(strip_tags($source['excerpt']), 500), 'url' => $source['url']])->all();
        return ['answer' => $this->provider->answer($question, $sourceData), 'sources' => $sourceData, 'confidence' => $confidence, 'refused' => false];
    }

    private function records($query, string $title, string $excerpt, string $url): array
    {
        return $query->limit(100)->get()->map(fn ($record): array => ['title' => (string) $record->{$title}, 'excerpt' => (string) ($record->{$excerpt} ?? ''), 'url' => $url])->all();
    }
}
