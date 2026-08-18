<?php

namespace App\Http\Controllers;

use App\Models\Curriculum;
use App\Models\EducationPlan;
use App\Models\School;
use App\Models\Student;
use App\Models\TeachingGroup;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $organizationId = $request->user()->organization_id;
        $like = "%{$query}%";

        $results = [
            'schools' => $query ? School::where('organization_id', $organizationId)->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('short_name', 'like', $like)->orWhere('city', 'like', $like))->orderBy('name')->limit(10)->get(['id', 'slug', 'name', 'city']) : collect(),
            'groups' => $query ? TeachingGroup::where('organization_id', $organizationId)->where('name', 'like', $like)->with('school:id,name')->orderBy('name')->limit(10)->get(['id', 'school_id', 'name']) : collect(),
            'curricula' => $query ? Curriculum::where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $organizationId))->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('external_identifier', 'like', $like))->orderBy('title')->limit(10)->get(['id', 'title', 'external_identifier']) : collect(),
            'educationPlans' => $query ? EducationPlan::where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $organizationId))->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('external_identifier', 'like', $like))->orderBy('title')->limit(10)->get(['id', 'title', 'external_identifier']) : collect(),
            'students' => $query ? Student::where('organization_id', $organizationId)->where(fn ($q) => $q->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('class_name', 'like', $like))->with('school:id,name')->orderBy('last_name')->limit(10)->get(['id', 'school_id', 'first_name', 'last_name', 'class_name']) : collect(),
        ];

        return Inertia::render('Search/Index', ['query' => $query, 'results' => $results]);
    }
}
