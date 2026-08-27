<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\RoleDashboardResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RoleDashboardResolverTest extends TestCase
{
    #[DataProvider('roleDashboardProvider')]
    public function test_roles_resolve_to_their_dashboard(string $role, string $route): void
    {
        $user = new User(['account_type' => $role, 'status' => 'active']);

        $this->assertSame($route, app(RoleDashboardResolver::class)->routeFor($user));
    }

    public static function roleDashboardProvider(): array
    {
        return [
            ['etudiant', 'student.dashboard'],
            ['chercheur', 'researcher.dashboard'],
            ['universite', 'university.dashboard'],
            ['evaluateur', 'evaluator.dashboard'],
            ['partenaire', 'partner.dashboard'],
            ['agent_dossier', 'agent_dossier.dashboard'],
            ['agent_finance', 'agent_finance.dashboard'],
            ['agent_recherche', 'agent_recherche.dashboard'],
            ['agent_communication', 'agent_communication.dashboard'],
            ['gestionnaire', 'gestionnaire.dashboard'],
            ['directeur_general', 'directeur_general.dashboard'],
            ['admin', 'filament.admin.pages.dashboard'],
            ['super_admin', 'filament.admin.pages.dashboard'],
        ];
    }

    public function test_inactive_accounts_cannot_resolve_a_dashboard(): void
    {
        $user = new User(['account_type' => 'etudiant', 'status' => 'suspended']);

        $this->expectException(HttpException::class);
        app(RoleDashboardResolver::class)->routeFor($user);
    }
}
