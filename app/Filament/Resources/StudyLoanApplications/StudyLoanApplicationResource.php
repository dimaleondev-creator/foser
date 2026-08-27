<?php

namespace App\Filament\Resources\StudyLoanApplications;

use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\StudyLoanApplications\Pages\ManageStudyLoanApplications;
use App\Models\StudyLoanApplication;
use BackedEnum;

class StudyLoanApplicationResource extends BaseCrudResource
{
    protected static ?string $model = StudyLoanApplication::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-check';
    protected static ?string $navigationLabel = 'Demandes de prêts';
    protected static string $permission = 'finance.view';
    protected static ?string $managePermission = 'finance.manage';
    protected static array $fields = [
        ['name' => 'applicant_id', 'required' => true],
        ['name' => 'study_loan_id', 'required' => true],
        ['name' => 'reference', 'required' => true],
        ['name' => 'amount', 'type' => 'number', 'required' => true],
        ['name' => 'duration_months', 'type' => 'number', 'required' => true],
        ['name' => 'interest_rate', 'type' => 'number', 'required' => true],
        ['name' => 'grace_period_months', 'type' => 'number'],
        ['name' => 'status', 'required' => true],
        ['name' => 'decision_note', 'type' => 'textarea'],
    ];
    protected static array $tableColumns = [
        ['name' => 'reference'],
        ['name' => 'amount'],
        ['name' => 'duration_months', 'label' => 'Durée'],
        ['name' => 'interest_rate', 'label' => 'Taux'],
        ['name' => 'status'],
        ['name' => 'created_at', 'label' => 'Demandé le'],
    ];
    public static function getPages(): array { return ['index' => ManageStudyLoanApplications::route('/')]; }
}
