<?php

namespace Tests\Feature;

use App\Models\ProgramFaq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_answers_from_published_public_source(): void
    {
        ProgramFaq::create([
            'question' => 'Comment demander une aide financière ?',
            'answer' => 'Déposez votre dossier sur le portail pendant la période ouverte.',
            'status' => 'published',
            'sort_order' => 1,
        ]);

        $this->postJson(route('assistant.ask'), ['question' => 'Comment demander une aide financière ?'])
            ->assertOk()
            ->assertJsonPath('data.refused', false)
            ->assertJsonPath('data.sources.0.title', 'Comment demander une aide financière ?')
            ->assertJsonPath('data.sources.0.url', route('faq'));
    }

    public function test_assistant_refuses_when_public_sources_are_insufficient(): void
    {
        $this->postJson(route('assistant.ask'), ['question' => 'Quel est le résultat secret de demain ?'])
            ->assertOk()
            ->assertJsonPath('data.refused', true)
            ->assertJsonPath('data.answer', null)
            ->assertJsonPath('data.sources', []);
    }

    public function test_assistant_validates_questions_and_get_page_is_available(): void
    {
        $this->get(route('assistant.index'))->assertOk()->assertSee('Assistant FOSER');
        $this->postJson(route('assistant.ask'), ['question' => 'x'])->assertUnprocessable()->assertJsonValidationErrors(['question']);
        $this->postJson(route('assistant.orientation'), ['question' => 'Quels programmes existent ?'])->assertOk()->assertJsonPath('data.mode', 'orientation');
        $this->postJson(route('assistant.checklist'), ['question' => 'Quels documents préparer ?'])->assertOk()->assertJsonPath('data.mode', 'checklist');
    }
}
