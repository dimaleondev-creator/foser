<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class StudentEligibilityService
{
    public function evaluate(User $student, string $programId, array $answers = []): array
    {
        $profile = DB::table('student_profiles')->where('user_id', $student->id)->first();
        $rules = DB::table('eligibility_rules')->where('program_id', $programId)->orderBy('sort_order')->get();
        $results = [];
        $requiresReview = false;

        foreach ($rules as $rule) {
            $parameters = json_decode((string) $rule->parameters, true) ?: [];
            $field = (string) ($parameters['field'] ?? $rule->rule_type);
            $value = $answers[$field] ?? $profile?->{$field} ?? null;
            $expected = $parameters['value'] ?? $parameters['values'] ?? null;
            $result = $this->compare($value, $rule->operator, $expected, $parameters);

            if ($result === null) {
                $requiresReview = true;
            }

            $results[] = [
                'label' => (string) ($parameters['label'] ?? $field),
                'passed' => $result,
                'value' => $value,
            ];
        }

        $failed = collect($results)->contains(fn (array $result): bool => $result['passed'] === false);

        return [
            'status' => $failed ? 'ineligible' : ($requiresReview ? 'review' : 'eligible'),
            'results' => $results,
            'message' => $failed
                ? 'Les critères connus indiquent que ce programme ne correspond pas à votre profil.'
                : ($requiresReview ? 'Votre situation nécessite une vérification par un agent FOSER.' : 'Les critères connus correspondent à votre profil.'),
        ];
    }

    private function compare(mixed $value, string $operator, mixed $expected, array $parameters): ?bool
    {
        if ($operator === 'exists') {
            return filled($value) === (bool) $expected;
        }

        if ($value === null || $expected === null) {
            return null;
        }

        return match ($operator) {
            'equals' => (string) $value === (string) $expected,
            'in' => in_array((string) $value, array_map('strval', (array) $expected), true),
            'not_in' => ! in_array((string) $value, array_map('strval', (array) $expected), true),
            'gte' => is_numeric($value) && is_numeric($expected) && (float) $value >= (float) $expected,
            'lte' => is_numeric($value) && is_numeric($expected) && (float) $value <= (float) $expected,
            'between' => is_numeric($value) && is_array($expected) && count($expected) === 2 && (float) $value >= (float) $expected[0] && (float) $value <= (float) $expected[1],
            default => null,
        };
    }
}
