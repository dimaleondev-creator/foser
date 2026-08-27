<?php

namespace App\Http\Controllers;

use App\Services\RagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssistantController extends Controller
{
    public function index(): View
    {
        return view('assistant.index');
    }

    public function ask(Request $request, RagService $rag): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'min:3', 'max:1000']]);
        $result = $rag->answer($data['question']);

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
        $result = $rag->answer($data['question']);
        $result['mode'] = $mode;
        return response()->json(['data' => $result]);
    }
}