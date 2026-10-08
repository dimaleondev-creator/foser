<?php

namespace App\Services;

use App\Models\News;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class NewsPublicationService
{
    public function publish(News $news, ?User $actor = null): void
    {
        $actor ??= Auth::user();
        Gate::forUser($actor)->authorize('content.publish');
        if (blank($news->title) || blank($news->slug) || blank($news->body)) {
            throw ValidationException::withMessages(['news' => 'Le titre, le slug et le contenu sont requis avant publication.']);
        }

        $news->forceFill([
            'author_id' => $news->author_id ?: $actor?->id,
            'status' => 'published',
            'visibility' => $news->visibility ?: 'public',
            'published_at' => $news->published_at ?: now(),
        ])->save();
    }

    public function unpublish(News $news, ?User $actor = null): void
    {
        Gate::forUser($actor ?? Auth::user())->authorize('content.publish');
        $news->forceFill(['status' => 'draft'])->save();
    }

    public function archive(News $news, ?User $actor = null): void
    {
        Gate::forUser($actor ?? Auth::user())->authorize('content.publish');
        $news->forceFill(['status' => 'archived'])->save();
    }
}
