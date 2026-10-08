<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiResponseEnvelope
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('api/v1/assistant/*') || $response->getStatusCode() === 204
            || ! str_contains((string) $response->headers->get('Content-Type'), 'application/json')) {
            return $response;
        }

        $body = json_decode($response->getContent() ?: '', true);
        if (! is_array($body)) {
            return $response;
        }

        $hasData = array_key_exists('data', $body);
        $pagination = $body['pagination'] ?? $body['meta'] ?? null;
        $envelope = [
            'success' => $response->getStatusCode() < 400,
            'data' => $hasData ? $body['data'] : null,
            'message' => $body['message'] ?? null,
            'errors' => $body['errors'] ?? null,
            'pagination' => $pagination,
            ...array_diff_key($body, array_flip(['success', 'data', 'message', 'errors', 'pagination', 'meta'])),
        ];
        if (array_key_exists('meta', $body)) {
            $envelope['meta'] = $pagination;
        }

        $response->setContent(json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $response;
    }
}