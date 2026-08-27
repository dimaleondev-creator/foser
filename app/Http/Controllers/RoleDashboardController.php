<?php

namespace App\Http\Controllers;

use App\Services\RoleDashboardResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoleDashboardController extends Controller
{
    public function __construct(private readonly RoleDashboardResolver $resolver) {}

    public function show(string $role): View
    {
        $user = Auth::user();
        abort_unless($user && $this->resolver->roleFor($user) === $role, 403);

        return view('dashboards.role', [
            'role' => $role,
            'user' => $user,
            'stats' => $this->stats($role),
        ]);
    }

    private function stats(string $role): array
    {
        return match ($role) {
            'evaluateur' => [
                'Dossiers à évaluer' => $this->countIfExists('applications'),
                'Évaluations' => $this->countIfExists('evaluations'),
            ],
            'partenaire' => [
                'Programmes' => $this->countIfExists('programs'),
                'Projets' => $this->countIfExists('research_projects'),
            ],
            'agent_finance' => [
                'Engagements' => $this->countIfExists('financial_commitments'),
                'Décaissements' => $this->countIfExists('disbursements'),
            ],
            'agent_recherche' => [
                'Chercheurs' => $this->countIfExists('researcher_profiles'),
                'Projets' => $this->countIfExists('research_projects'),
            ],
            'agent_communication' => [
                'Contenus' => $this->countIfExists('contents'),
                'Appels' => $this->countIfExists('calls'),
            ],
            'directeur_general' => [
                'Étudiants inscrits' => $this->countIfExists('student_profiles'),
                'Dossiers déposés' => $this->countIfExists('applications'),
                'Projets de recherche' => $this->countIfExists('research_projects'),
            ],
            default => [
                'Dossiers' => $this->countIfExists('applications'),
                'Utilisateurs' => $this->countIfExists('users'),
            ],
        };
    }

    private function countIfExists(string $table): int
    {
        return DB::getSchemaBuilder()->hasTable($table) ? DB::table($table)->count() : 0;
    }
}
