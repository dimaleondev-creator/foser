<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\User;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class AdminApplicationController extends Controller
{
    public function show(Application $application): View
    {
        $application->load(['applicant', 'program']);
        $student = DB::table('student_profiles')->where('user_id', $application->applicant_id)->first();
        $university = $student?->university_id ? DB::table('universities')->where('id', $student->university_id)->first() : null;
        $call = DB::table('calls')->where('id', $application->call_id)->first();
        $documents = DB::table('application_documents')->where('application_id', $application->id)->orderBy('created_at')->get();
        $history = DB::table('application_status_histories')->where('application_id', $application->id)->latest('changed_at')->get();
        $evaluations = DB::table('evaluations')->join('users', 'users.id', '=', 'evaluations.evaluator_id')->where('application_id', $application->id)->select('evaluations.*', 'users.name as evaluator_name')->get();
        foreach ($evaluations as $evaluation) {
            $evaluation->scores = DB::table('evaluation_scores')->join('evaluation_criteria', 'evaluation_criteria.id', '=', 'evaluation_scores.criterion_id')->where('evaluation_scores.evaluation_id', $evaluation->id)->select('evaluation_criteria.name as criterion_name', 'evaluation_criteria.maximum_score', 'evaluation_scores.score', 'evaluation_scores.comment')->orderBy('evaluation_criteria.name')->get();
        }
        $evaluators = DB::table('users')->where('status', 'active')->where('account_type', 'evaluateur')->whereExists(fn ($query) => $query->selectRaw('1')->from('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')->whereColumn('model_has_roles.model_id', 'users.id')->where('model_has_roles.model_type', User::class)->where('roles.name', 'evaluateur'))->orderBy('name')->get(['id', 'name', 'evaluation_expertise']);
        $evaluators->transform(function (object $evaluator): object {
            $evaluator->evaluation_expertise = json_decode((string) $evaluator->evaluation_expertise, true) ?: [];
            return $evaluator;
        });
        $result = DB::table('application_results')->where('application_id', $application->id)->first();
        $award = DB::table('application_awards')->where('application_id', $application->id)->first();
        $commitment = DB::table('financial_commitments')->where('application_id', $application->id)->first();
        $disbursement = $commitment ? DB::table('disbursements')->where('commitment_id', $commitment->id)->first() : null;

        return view('admin.applications.detail', compact('application', 'student', 'university', 'call', 'documents', 'history', 'evaluations', 'evaluators', 'result', 'award', 'commitment', 'disbursement'));
    }
}
