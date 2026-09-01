<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSchoolObservationScaleRequest;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class CustomProcessCompetenceController extends Controller
{
    public function update(UpdateSchoolObservationScaleRequest $request, School $school): RedirectResponse
    {
        $this->authorize('update', $school);
        $data = $request->validated();

        DB::transaction(function () use ($school, $data): void {
            $school->update(['observation_scale_interval_count' => $data['observation_scale_interval_count']]);
            $submittedIds = collect($data['competences'])->pluck('id')->filter();

            $school->customProcessCompetences()->whereNotIn('id', $submittedIds)->update(['is_active' => false]);

            foreach ($data['competences'] as $competence) {
                if (! empty($competence['id'])) {
                    $school->customProcessCompetences()->whereKey($competence['id'])->update(collect($competence)->only(['text', 'position', 'is_active'])->all());

                    continue;
                }

                $school->customProcessCompetences()->create(collect($competence)->only(['text', 'position', 'is_active'])->all());
            }
        });

        return back()->with('success', 'Beobachtungsskala und eigene Prozesskompetenzen wurden gespeichert.');
    }
}
