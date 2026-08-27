<?php

namespace App\Http\Controllers;

use App\Models\StudyLoan;
use App\Services\StudyLoanCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StudyLoanController extends Controller
{
    public function index(): View { return view('student.study-loans', ['loans'=>StudyLoan::where('status','active')->with('program')->orderBy('name')->get()]); }
    public function simulate(Request $request, StudyLoanCalculator $calculator): \Illuminate\Http\JsonResponse { $data=$request->validate(['amount'=>'required|numeric|min:1','duration_months'=>'required|integer|min:1|max:120','interest_rate'=>'required|numeric|min:0|max:100','grace_period_months'=>'nullable|integer|min:0|max:24']); return response()->json($calculator->simulate((float)$data['amount'],(int)$data['duration_months'],(float)$data['interest_rate'],(int)($data['grace_period_months']??0))); }
    public function apply(Request $request, StudyLoanCalculator $calculator): RedirectResponse
    {
        $data=$request->validate(['study_loan_id'=>'required|uuid|exists:study_loans,id','amount'=>'required|numeric|min:1','duration_months'=>'required|integer|min:1|max:120','interest_rate'=>'required|numeric|min:0|max:100','grace_period_months'=>'nullable|integer|min:0|max:24']);
        $loan=StudyLoan::whereKey($data['study_loan_id'])->where('status','active')->firstOrFail();
        abort_unless(!$loan->maximum_amount || $data['amount'] <= $loan->maximum_amount,422,'Le montant dépasse le plafond du prêt.');
        $simulation=$calculator->simulate((float)$data['amount'],(int)$data['duration_months'],(float)$data['interest_rate'],(int)($data['grace_period_months']??0));
        $id=(string)Str::uuid();
        DB::transaction(function() use($data,$simulation,$id):void { DB::table('study_loan_applications')->insert(['id'=>$id,'applicant_id'=>auth()->id(),'study_loan_id'=>$data['study_loan_id'],'reference'=>'PRET-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),'amount'=>$data['amount'],'duration_months'=>$data['duration_months'],'interest_rate'=>$data['interest_rate'],'grace_period_months'=>$data['grace_period_months']??0,'monthly_payment'=>$simulation['monthly_payment'],'total_interest'=>$simulation['total_interest'],'total_repayment'=>$simulation['total_repayment'],'first_due_at'=>$simulation['first_due_at'],'last_due_at'=>$simulation['last_due_at'],'simulation'=>json_encode($simulation),'status'=>'soumis','created_at'=>now(),'updated_at'=>now()]); foreach($simulation['schedule'] as $row){DB::table('study_loan_installments')->insert(['id'=>(string)Str::uuid(),'study_loan_application_id'=>$id,...$row,'created_at'=>now(),'updated_at'=>now()]);} });
        return redirect()->route('student.study-loans')->with('status','Votre demande de prêt a été soumise.');
    }
}
