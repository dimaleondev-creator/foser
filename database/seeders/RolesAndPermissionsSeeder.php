<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    private const PERMISSIONS = [
        'users.view', 'users.create', 'users.update', 'users.delete',
        'applications.view', 'applications.create', 'applications.update',
        'applications.submit', 'applications.validate', 'applications.reject', 'applications.edit', 'applications.verify', 'applications.assign_evaluator', 'applications.evaluate', 'applications.review_commission', 'applications.decide', 'applications.publish_result', 'applications.award', 'applications.finance', 'applications.disburse',
        'documents.view', 'documents.upload', 'documents.validate', 'documents.delete',
        'calls.view', 'calls.create', 'calls.update', 'calls.publish', 'calls.close',
        'evaluations.view', 'evaluations.create', 'evaluations.update', 'evaluations.validate',
        'programs.view', 'programs.create', 'programs.update', 'programs.delete',
        'finance.view', 'finance.manage', 'finance.export',
        'research.view', 'research.manage', 'research.evaluate', 'researcher.dashboard', 'researcher.profile.view', 'researcher.profile.edit', 'researcher.projects.view', 'researcher.projects.create', 'researcher.projects.edit', 'researcher.projects.submit', 'researcher.publications.manage', 'researcher.calls.view', 'researcher.conventions.view', 'researcher.disbursements.view', 'researcher.notifications.view', 'researcher.messages.view',
        'university.view', 'university.manage', 'university.students.view', 'university.students.validate',
        'university.applications.validate', 'university.imports.create', 'university.reports.view', 'university.messages.create',
        'content.view', 'content.create', 'content.update', 'content.publish', 'content.delete',
        'reports.view', 'reports.export', 'notifications.send', 'audit.view', 'settings.manage',
    ];

    private const ROLE_PERMISSIONS = [
        'super_admin' => '*',
        'admin' => self::PERMISSIONS,
        'directeur_general' => [
            'users.view', 'applications.view', 'applications.validate', 'applications.reject',
            'calls.view', 'calls.publish', 'calls.close', 'programs.view', 'programs.create',
            'programs.update', 'finance.view', 'research.view', 'content.view', 'content.publish',
            'reports.view', 'reports.export', 'audit.view',
        ],
        'gestionnaire' => [
            'users.view', 'applications.view', 'applications.update', 'applications.validate',
            'documents.view', 'documents.validate', 'calls.view', 'programs.view',
            'reports.view', 'notifications.send',
        ],
        'agent_dossier' => [
            'applications.view', 'applications.update', 'applications.validate', 'applications.reject',
            'documents.view', 'documents.upload', 'documents.validate', 'calls.view',
        ],
        'agent_finance' => ['applications.view', 'finance.view', 'finance.manage', 'finance.export', 'reports.view'],
        'agent_recherche' => ['research.view', 'research.manage', 'research.evaluate', 'applications.view'],
        'agent_communication' => [
            'content.view', 'content.create', 'content.update', 'content.publish', 'content.delete',
            'calls.view', 'notifications.send',
        ],
        'evaluateur' => ['applications.view', 'evaluations.view', 'evaluations.create', 'evaluations.update', 'evaluations.validate', 'applications.evaluate', 'research.view', 'research.evaluate'],
        'etudiant' => ['applications.view', 'applications.create', 'applications.update', 'applications.submit', 'documents.view', 'documents.upload', 'calls.view'],
        'chercheur' => ['applications.view', 'applications.create', 'applications.update', 'applications.submit', 'documents.view', 'documents.upload', 'calls.view', 'research.view', 'research.manage'],
        'researcher' => ['researcher.dashboard', 'researcher.profile.view', 'researcher.profile.edit', 'researcher.projects.view', 'researcher.projects.create', 'researcher.projects.edit', 'researcher.projects.submit', 'researcher.publications.manage', 'researcher.calls.view', 'researcher.conventions.view', 'researcher.disbursements.view', 'researcher.notifications.view', 'researcher.messages.view', 'research.view', 'research.manage'],
        'universite' => ['university.view', 'university.manage', 'university.students.view', 'university.students.validate', 'university.applications.validate', 'university.imports.create', 'university.reports.view', 'university.messages.create', 'users.view', 'applications.view', 'applications.validate', 'documents.view', 'calls.view', 'research.view'],
        'partenaire' => ['calls.view', 'programs.view', 'research.view', 'content.view'],
    ];

    public function run(): void
    {
        $permissions = collect(self::PERMISSIONS)
            ->mapWithKeys(fn (string $permission): array => [$permission => Permission::findOrCreate($permission, 'web')]);

        foreach (self::ROLE_PERMISSIONS as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName, 'web');

            if ($rolePermissions === '*') {
                $role->syncPermissions($permissions->values()->all());
                continue;
            }

            $role->syncPermissions($permissions->only($rolePermissions)->values()->all());
        }
    }
}
