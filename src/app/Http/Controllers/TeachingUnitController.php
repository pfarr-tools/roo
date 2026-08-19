<?php

namespace App\Http\Controllers;

use App\Models\TeachingGroup;
use App\Models\TeachingUnit;
use App\Models\EducationPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeachingUnitController extends Controller
{
    public function index(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $units = TeachingUnit::query()
            ->where('organization_id', $request->user()->organization_id)
            ->with(['group:id,name,school_year_id', 'group.schoolYear:id,name', 'sourceCurriculumTopic:id,title', 'educationPlan:id,title,external_identifier'])
            ->withCount('lessons')
            ->when($query !== '', fn ($builder) => $builder->where(fn ($queryBuilder) => $queryBuilder
                ->where('title', 'like', "%{$query}%")
                ->orWhereHas('group', fn ($group) => $group->where('name', 'like', "%{$query}%"))))
            ->orderBy('title')
            ->get(['id', 'teaching_group_id', 'education_plan_id', 'source_curriculum_topic_id', 'title', 'position', 'notes', 'copied_from_id']);

        return Inertia::render('TeachingUnits/Index', [
            'units' => $units,
            'educationPlans' => EducationPlan::where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id))->orderBy('title')->get(['id', 'title', 'external_identifier']),
            'filters' => ['q' => $query],
        ]);
    }

    public function update(Request $request, TeachingUnit $teachingUnit): RedirectResponse
    {
        abort_unless($teachingUnit->organization_id === $request->user()->organization_id, 404);
        $group = $teachingUnit->group;
        $this->authorize('update', $group);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'notes' => ['nullable', 'string'], 'education_plan_id' => ['nullable', 'integer']]);
        if (isset($data['education_plan_id'])) {
            abort_unless(EducationPlan::whereKey($data['education_plan_id'])->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id))->exists(), 422);
        }
        $teachingUnit->update($data);

        return back()->with('success', 'Unterrichtseinheit wurde gespeichert.');
    }

    public function destroy(Request $request, TeachingUnit $teachingUnit): RedirectResponse
    {
        abort_unless($teachingUnit->organization_id === $request->user()->organization_id, 404);
        $this->authorize('update', $teachingUnit->group);
        $teachingUnit->delete();

        return back()->with('success', 'Unterrichtseinheit wurde gelöscht.');
    }
}
