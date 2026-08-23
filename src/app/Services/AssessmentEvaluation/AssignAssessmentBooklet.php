<?php

namespace App\Services\AssessmentEvaluation;

use App\Models\AssessmentBooklet;
use App\Models\TeachingGroup;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssignAssessmentBooklet
{
    public function handle(AssessmentBooklet $booklet, TeachingGroup $teachingGroup, ?int $studentId): AssessmentBooklet
    {
        try {
            return DB::transaction(function () use ($booklet, $teachingGroup, $studentId): AssessmentBooklet {
                $lockedBooklet = AssessmentBooklet::query()->lockForUpdate()->findOrFail($booklet->getKey());

                if ($studentId !== null && ! $teachingGroup->students()->whereKey($studentId)->exists()) {
                    throw ValidationException::withMessages(['student_id' => 'Die ausgewählte Schüler:in gehört nicht zu dieser Unterrichtsgruppe.']);
                }

                $this->ensureAvailable($lockedBooklet, $studentId, $lockedBooklet->status);
                $lockedBooklet->update(['student_id' => $studentId]);

                return $lockedBooklet;
            });
        } catch (QueryException $exception) {
            $this->throwDuplicateAssignment($exception);
        }
    }

    public function updateStatus(AssessmentBooklet $booklet, string $status): AssessmentBooklet
    {
        try {
            return DB::transaction(function () use ($booklet, $status): AssessmentBooklet {
                $lockedBooklet = AssessmentBooklet::query()->lockForUpdate()->findOrFail($booklet->getKey());
                $this->ensureAvailable($lockedBooklet, $lockedBooklet->student_id, $status);
                $lockedBooklet->update(['status' => $status]);

                return $lockedBooklet;
            });
        } catch (QueryException $exception) {
            $this->throwDuplicateAssignment($exception, 'status');
        }
    }

    private function ensureAvailable(AssessmentBooklet $booklet, ?int $studentId, string $status): void
    {
        if ($status !== 'open' || $studentId === null) {
            return;
        }

        $assignedElsewhere = AssessmentBooklet::query()
            ->where('assessment_id', $booklet->assessment_id)
            ->where('status', 'open')
            ->where('student_id', $studentId)
            ->where($booklet->getQualifiedKeyName(), '!=', $booklet->getKey())
            ->exists();

        if ($assignedElsewhere) {
            throw ValidationException::withMessages(['student_id' => 'Diese Schüler:in ist bereits einem offenen Booklet zugeordnet.']);
        }
    }

    private function throwDuplicateAssignment(QueryException $exception, string $key = 'student_id'): never
    {
        if (str_contains($exception->getMessage(), 'assessment_booklets_active_student_unique')) {
            throw ValidationException::withMessages([$key => 'Diese Schüler:in ist bereits einem offenen Booklet zugeordnet.']);
        }

        throw $exception;
    }
}
