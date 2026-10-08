<?php

namespace App\Http\Controllers;

use App\Services\RagService;
use App\Models\AIInteraction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssistantController extends Controller
{
    public function index(): View
    {
        return view('assistant.index', ['audience' => 'public']);
    }

    public function student(): View { return view('assistant.index', ['audience' => 'student']); }
    public function researcher(): View { return view('assistant.index', ['audience' => 'researcher']); }
    public function university(): View { return view('assistant.index', ['audience' => 'university']); }

    public function ask(Request $request, RagService $rag): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'min:3', 'max:1000']]);
        $started = hrtime(true);
        $result = $rag->answer($data['question']);
        $interaction = $this->logInteraction($request, $data['question'], $result, 'assistant', $started);
        $result['interaction_id'] = $interaction->id;

        if ($request->expectsJson()) return response()->json(['data' => $result]);

        return redirect()->route('assistant.index')->with('assistant', $result);
    }

    public function orientation(Request $request, RagService $rag): JsonResponse
    {
        return $this->jsonAnswer($request, $rag, 'orientation');
    }

    public function checklist(Request $request, RagService $rag): JsonResponse
    {
        return $this->jsonAnswer($request, $rag, 'checklist');
    }

    private function jsonAnswer(Request $request, RagService $rag, string $mode): JsonResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'min:3', 'max:1000']]);
        $started = hrtime(true);
        $result = $rag->answer($data['question']);
        $result['mode'] = $mode;
        $result['interaction_id'] = $this->logInteraction($request, $data['question'], $result, $mode, $started)->id;
        return response()->json(['data' => $result]);
    }

    public function feedback(Request $request): JsonResponse
    {
        $data = $request->validate(['interaction_id' => ['required', 'uuid'], 'feedback' => ['required', 'integer', 'in:-1,1'], 'comment' => ['nullable', 'string', 'max:1000']]);
        $interaction = AIInteraction::query()->whereKey($data['interaction_id'])->where(function ($query) use ($request): void {
            $query->where('session_id', $request->session()->getId());
            if ($request->user()) $query->orWhere('user_id', $request->user()->id);
        })->firstOrFail();
        $interaction->update(['feedback' => $data['feedback'], 'feedback_comment' => $data['comment'] ?? null]);
        return response()->json(['message' => 'Merci pour votre retour.']);
    }

    private function logInteraction(Request $request, string $question, array $result, string $feature, int $started): AIInteraction
    {
        return AIInteraction::create(['user_id' => $request->user()?->id, 'session_id' => $request->session()->getId(), 'provider' => config('ai.provider'), 'model' => config('ai.providers.'.config('ai.provider').'.model'), 'feature' => $feature, 'question' => mb_substr($question, 0, 1000), 'response' => isset($result['answer']) ? mb_substr((string) $result['answer'], 0, 20000) : null, 'sources' => $result['sources'] ?? [], 'risk_level' => $result['refused'] ?? false ? 'guarded' : 'normal', 'latency_ms' => (int) round((hrtime(true) - $started) / 1_000_000), 'status' => 'completed']);
    }
}