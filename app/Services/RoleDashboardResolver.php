<?php

namespace App\Services;

use App\Models\User;

class RoleDashboardResolver
{
    private const DASHBOARDS = [
        'etudiant' => 'student.dashboard',
        'chercheur' => 'researcher.dashboard',
        'researcher' => 'researcher.dashboard',
        'universite' => 'university.dashboard',
        'evaluateur' => 'evaluator.dashboard',
        'partenaire' => 'partner.dashboard',
        'agent_dossier' => 'agent_dossier.dashboard',
        'agent_finance' => 'agent_finance.dashboard',
        'agent_recherche' => 'agent_recherche.dashboard',
        'agent_communication' => 'agent_communication.dashboard',
        'gestionnaire' => 'gestionnaire.dashboard',
        'directeur_general' => 'directeur_general.dashboard',
        'admin' => 'filament.admin.pages.dashboard',
        'super_admin' => 'filament.admin.pages.dashboard',
    ];

    public function routeFor(User $user): string
    {
        $role = $this->roleFor($user);

        return self::DASHBOARDS[$role];
    }

    public function roleFor(User $user): string
    {
        $role = $user->account_type ?: $user->getRoleNames()->first();

        abort_unless(! in_array($user->status, ['suspended', 'disabled', 'inactive'], true), 403, 'Votre compte est désactivé.');
        abort_unless(isset(self::DASHBOARDS[$role]), 403, 'Aucun espace n’est associé à votre compte.');

        return (string) $role;
    }
}
