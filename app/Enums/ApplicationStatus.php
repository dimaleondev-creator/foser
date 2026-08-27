<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case DRAFT = 'draft';
    case DOCUMENTS_PENDING = 'documents_pending';
    case COMPLETENESS_CHECK = 'completeness_check';
    case SUBMITTED = 'submitted';
    case UNIVERSITY_REVIEW = 'university_review';
    case EVALUATOR_ASSIGNMENT = 'evaluator_assignment';
    case EVALUATION = 'evaluation';
    case COMMISSION_REVIEW = 'commission_review';
    case DECISION_MADE = 'decision_made';
    case RESULT_PUBLISHED = 'result_published';
    case AWARDED = 'awarded';
    case FINANCE_PENDING = 'finance_pending';
    case COMMITTED = 'committed';
    case DISBURSEMENT_PENDING = 'disbursement_pending';
    case DISBURSED = 'disbursed';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon', self::DOCUMENTS_PENDING => 'Pièces à compléter',
            self::COMPLETENESS_CHECK => 'Contrôle de complétude', self::SUBMITTED => 'Soumise',
            self::UNIVERSITY_REVIEW => 'Vérification universitaire', self::EVALUATOR_ASSIGNMENT => 'Affectation des évaluateurs', self::EVALUATION => 'Évaluation',
            self::COMMISSION_REVIEW => 'Commission', self::DECISION_MADE => 'Décision prise', self::RESULT_PUBLISHED => 'Résultat publié',
            self::AWARDED => 'Attribuée', self::FINANCE_PENDING => 'Finance en attente', self::COMMITTED => 'Engagée',
            self::DISBURSEMENT_PENDING => 'Décaissement en préparation', self::DISBURSED => 'Décaissée',
            self::REJECTED => 'Rejetée', self::CANCELLED => 'Annulée',
        };
    }
}