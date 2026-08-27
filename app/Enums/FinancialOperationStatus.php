<?php

namespace App\Enums;

enum FinancialOperationStatus: string
{
    case BROUILLON = 'brouillon';
    case SOUMIS = 'soumis';
    case VALIDE = 'valide';
    case REJETE = 'rejete';
    case EXECUTE = 'execute';
    case ANNULE = 'annule';

    public function label(): string
    {
        return match ($this) {
            self::BROUILLON => 'Brouillon', self::SOUMIS => 'Soumis', self::VALIDE => 'Validé',
            self::REJETE => 'Rejeté', self::EXECUTE => 'Exécuté', self::ANNULE => 'Annulé',
        };
    }
}
