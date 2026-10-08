<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DocumentAccessService
{
    public function scope(Builder $query, User $user): Builder
    {
        $query->where('status', 'published');

        if ($user->hasAnyRole([
            'super_admin', 'admin', 'directeur_general', 'gestionnaire',
            'agent_dossier', 'agent_finance', 'agent_recherche',
        ])) {
            return $query;
        }

        return $query->where(function (Builder $accessQuery) use ($user): void {
            $accessQuery->where(function (Builder $publicQuery): void {
                $publicQuery->where('visibility', 'public');
            })->orWhere('uploaded_by', $user->id)
                ->orWhereIn('id', function ($documentQuery) use ($user): void {
                    $documentQuery->select('application_documents.document_id')
                        ->from('application_documents')
                        ->join('applications', 'applications.id', '=', 'application_documents.application_id')
                        ->where('applications.applicant_id', $user->id);
                })
                ->orWhereIn('id', function ($documentQuery) use ($user): void {
                    $documentQuery->select('research_project_documents.document_id')
                        ->from('research_project_documents')
                        ->join('research_projects', 'research_projects.id', '=', 'research_project_documents.research_project_id')
                        ->where(function ($projectQuery) use ($user): void {
                            $projectQuery->where('research_projects.principal_researcher_id', $user->id)
                                ->orWhereIn('research_projects.id', DB::table('research_project_members')
                                    ->select('research_project_id')
                                    ->where('user_id', $user->id));
                        });
                });

            if ($user->hasRole('universite')) {
                $accessQuery->orWhereIn('id', function ($documentQuery) use ($user): void {
                    $documentQuery->select('application_documents.document_id')
                        ->from('application_documents')
                        ->join('applications', 'applications.id', '=', 'application_documents.application_id')
                        ->join('student_profiles', 'student_profiles.user_id', '=', 'applications.applicant_id')
                        ->whereIn('student_profiles.university_id', function ($universityQuery) use ($user): void {
                            $universityQuery->select('university_id')
                                ->from('university_users')
                                ->where('user_id', $user->id);

                            if (filled($user->university_id)) {
                                $universityQuery->orWhere('university_id', $user->university_id);
                            }
                        });
                });
            }

            if ($user->hasRole('evaluateur')) {
                $accessQuery->orWhereIn('id', function ($documentQuery) use ($user): void {
                    $documentQuery->select('application_documents.document_id')
                        ->from('application_documents')
                        ->whereIn('application_id', DB::table('evaluations')
                            ->select('application_id')
                            ->where('evaluator_id', $user->id));
                });
            }
        });
    }
}