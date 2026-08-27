<?php

namespace Tests\Unit;

use App\Services\StudyLoanCalculator;
use Tests\TestCase;

class StudyLoanCalculatorTest extends TestCase
{
    public function test_it_builds_an_amortization_schedule(): void
    {
        $result = app(StudyLoanCalculator::class)->simulate(500000, 12, 0, 2);

        $this->assertSame(12, $result['number_of_installments']);
        $this->assertCount(14, $result['schedule']);
        $this->assertSame('deferred', $result['schedule'][0]['status']);
        $this->assertSame(0.0, $result['schedule'][0]['amount']);
        $this->assertSame(500000.0, round($result['total_repayment'], 2));
    }

    public function test_it_calculates_interest_for_a_positive_rate(): void
    {
        $result = app(StudyLoanCalculator::class)->simulate(500000, 12, 5);

        $this->assertGreaterThan(0, $result['total_interest']);
        $this->assertSame(12, count(array_filter($result['schedule'], fn (array $row): bool => $row['status'] === 'pending')));
    }
}
