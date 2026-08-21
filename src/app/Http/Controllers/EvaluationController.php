<?php
namespace App\Http\Controllers;
use App\Models\ReportPeriod;
use App\Models\StudentEvaluation;
use App\Models\TeachingGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
class EvaluationController extends Controller
{
    public function createPeriod(TeachingGroup $teachingGroup) { $this->authorize('update',$teachingGroup); return Inertia::render('Evaluations/PeriodForm',['group'=>$teachingGroup]); }
    public function storePeriod(Request $request, TeachingGroup $teachingGroup) { $this->authorize('update',$teachingGroup); $data=$request->validate(['label'=>['required','string','max:100'],'starts_on'=>['required','date'],'ends_on'=>['required','date','after_or_equal:starts_on']]); DB::transaction(function () use ($data, $teachingGroup): void { $period=$teachingGroup->reportPeriods()->create([...$data,'organization_id'=>$teachingGroup->organization_id]); $teachingGroup->students()->get()->each(fn ($student) => $period->evaluations()->create(['student_id'=>$student->id])); }); return to_route('teaching-groups.show',$teachingGroup)->with('success','Bewertungszeitraum wurde angelegt.'); }
    public function edit(TeachingGroup $teachingGroup, StudentEvaluation $evaluation) { $this->authorize('update',$teachingGroup); abort_unless($evaluation->period->teaching_group_id===$teachingGroup->id,404); return Inertia::render('Evaluations/Edit',['group'=>$teachingGroup,'evaluation'=>$evaluation->load('student','period')]); }
    public function update(Request $request, TeachingGroup $teachingGroup, StudentEvaluation $evaluation) { $this->authorize('update',$teachingGroup); abort_unless($evaluation->period->teaching_group_id===$teachingGroup->id,404); $data=$request->validate(['draft_text'=>['nullable','string','max:10000'],'teacher_note'=>['nullable','string','max:5000'],'status'=>['required','in:draft,confirmed']]); if ($data['status']==='confirmed') $data['confirmed_at']=now(); else $data['confirmed_at']=null; $evaluation->update($data); return back()->with('success','Bewertungsentwurf wurde gespeichert.'); }
}
