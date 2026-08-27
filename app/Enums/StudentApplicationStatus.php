<?php

namespace App\Enums;

enum StudentApplicationStatus: string
{
    case BROUILLON = 'brouillon';
    case SOUMIS = 'soumis';
    case VERIFICATION = 'verification';
    case RECEVABLE = 'recevable';
    case INCOMPLET = 'incomplet';
    case COMPLEMENT = 'complement';
    case EVALUATION = 'evaluation';
    case VALIDE = 'valide';
    case REJETE = 'rejete';
    case DECISION = 'decision';
    case APPROUVE = 'approuve';
    case ENGAGE = 'engage';
    case DECAISSE = 'decaisse';
    case PAYE = 'paye';
    case ARCHIVE = 'archive';
    case CLOTURE = 'cloture';

    public function label(): string
    {
        return match ($this) {
            self::BROUILLON => 'Brouillon',
            self::SOUMIS => 'Soumis',
            self::VERIFICATION => 'Vérification',
            self::RECEVABLE => 'Recevable',
            self::INCOMPLET => 'Incomplet',
            self::COMPLEMENT => 'Complément demandé',
            self::EVALUATION => 'Évaluation',
            self::VALIDE => 'Validé',
            self::REJETE => 'Rejeté',
            self::DECISION => 'Décision',
            self::APPROUVE => 'Approuvé',
            self::ENGAGE => 'Engagé',
            self::DECAISSE => 'Décaissé',
            self::PAYE => 'Payé',
            self::CLOTURE => 'Clôturé',
            self::ARCHIVE => 'Archivé',
        };
    }

    public function progress(): int
    {
        return match ($this) {
            self::BROUILLON => 10,
            self::SOUMIS => 20,
            self::VERIFICATION, self::RECEVABLE, self::INCOMPLET, self::COMPLEMENT => 35,
            self::EVALUATION => 55,
            self::VALIDE, self::DECISION => 70,
            self::APPROUVE => 80,
            self::ENGAGE => 90,
            self::DECAISSE => 95,
            self::PAYE, self::CLOTURE, self::ARCHIVE => 100,
            self::REJETE => 0,
        };
    }
}
