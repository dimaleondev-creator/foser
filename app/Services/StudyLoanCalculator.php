<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class StudyLoanCalculator
{
    public function simulate(float $amount, int $months, float $annualRate, int $graceMonths = 0, ?CarbonImmutable $start = null): array
    {
        if ($amount <= 0 || $months < 1 || $annualRate < 0 || $graceMonths < 0) {
            throw new \InvalidArgumentException('Paramètres de prêt invalides.');
        }
        $monthlyRate = $annualRate / 100 / 12;
        $payment = $monthlyRate == 0.0 ? $amount / $months : $amount * $monthlyRate / (1 - (1 + $monthlyRate) ** -$months);
        $balance = $amount;
        $schedule = [];
        $start ??= CarbonImmutable::today();
        for ($index = 1; $index <= $months + $graceMonths; $index++) {
            $due = $start->addMonths($index);
            if ($index <= $graceMonths) {
                $schedule[] = ['installment_number'=>$index,'due_at'=>$due->toDateString(),'principal_amount'=>0.0,'interest_amount'=>0.0,'amount'=>0.0,'status'=>'deferred'];
                continue;
            }
            $interest = $balance * $monthlyRate;
            $principal = min($balance, $payment - $interest);
            $installment = $principal + $interest;
            $balance = max(0, $balance - $principal);
            $schedule[] = ['installment_number'=>$index,'due_at'=>$due->toDateString(),'principal_amount'=>round($principal,2),'interest_amount'=>round($interest,2),'amount'=>round($installment,2),'status'=>'pending'];
        }
        if ($monthlyRate == 0.0) {
            $pendingIndexes = array_keys(array_filter($schedule, fn (array $row): bool => $row['status'] === 'pending'));
            $lastIndex = end($pendingIndexes);
            $schedule[$lastIndex]['amount'] = round($amount - array_sum(array_column(array_slice($schedule, 0, $lastIndex), 'amount')), 2);
            $schedule[$lastIndex]['principal_amount'] = $schedule[$lastIndex]['amount'];
        }
        $totalRepayment = array_sum(array_column($schedule, 'amount'));
        return ['monthly_payment'=>round($payment,2),'total_interest'=>round($totalRepayment-$amount,2),'total_repayment'=>round($totalRepayment,2),'number_of_installments'=>$months,'first_due_at'=>$schedule[0]['due_at'],'last_due_at'=>end($schedule)['due_at'],'schedule'=>$schedule];
    }
}
