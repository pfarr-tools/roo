<?php

namespace App\Http\Controllers;

use App\Documents\DocumentOutputFormat;
use App\Documents\ParentLetterDocument;
use App\Models\TeachingUnit;
use App\Models\TeachingGroup;
use App\Models\UserPreference;
use App\Services\PhpOfficeDocumentRenderer;
use App\Services\QrCodeRenderer;
use App\Services\TeachingUnitPublicViewResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

final class ParentLetterController extends Controller
{
    public function download(Request $request, TeachingUnit $teachingUnit, TeachingUnitPublicViewResolver $resolver, PhpOfficeDocumentRenderer $renderer, QrCodeRenderer $qrCodeRenderer): Response
    {
        abort_unless($teachingUnit->organization_id === $request->user()->organization_id, 404);
        $this->authorize('update', $teachingUnit->group);

        $data = $request->validate([
            'format' => ['required', 'in:docx,odt'],
            'introduction_text' => ['nullable', 'string'],
        ]);
        UserPreference::updateOrCreate(
            ['user_id' => $request->user()->id, 'key' => 'documents.parent-letter.format'],
            ['value' => ['format' => $data['format']]],
        );
        $teachingUnit->update(['introduction_text' => $data['introduction_text'] ?? null]);

        $view = $resolver->resolve($teachingUnit->fresh(), CarbonImmutable::now('Europe/Berlin'));
        $publicUrl = $view->visiblePhaseResources->isNotEmpty() || $view->visiblePhaseLinks->isNotEmpty()
            ? URL::signedRoute('public.teaching-units.show', ['teachingUnit' => $teachingUnit])
            : null;
        $format = DocumentOutputFormat::from($data['format']);
        $document = new ParentLetterDocument(
            title: 'Elternbrief: '.$teachingUnit->title,
            group: $view->group->name,
            school: $view->school->name,
            creator: $view->creator?->name ?? $request->user()->name,
            introduction: (string) ($teachingUnit->introduction_text ?? ''),
            contentCompetencies: $view->competencies->filter(fn ($competency): bool => $this->competencyKind($competency) !== 'process')->map(fn ($competency): string => $this->competencyText($competency))->values()->all(),
            processCompetencies: $view->competencies->filter(fn ($competency): bool => $this->competencyKind($competency) === 'process')->map(fn ($competency): string => $this->competencyText($competency))->values()->all(),
            scheduledLessons: $view->scheduledLessons->map(fn (array $scheduledLesson): array => [
                'date' => $scheduledLesson['starts_at']->format('d.m.Y'),
                'time' => $scheduledLesson['starts_at']->format('H:i'),
                'title' => $scheduledLesson['lesson']->title,
            ])->all(),
            publicUrl: $publicUrl,
            qrPng: $publicUrl === null ? null : $qrCodeRenderer->png($publicUrl),
        );
        $contents = $renderer->render($document, $format);

        return response($contents, 200, [
            'Content-Type' => $format === DocumentOutputFormat::DOCX
                ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                : 'application/vnd.oasis.opendocument.text',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($view->group).'.'.$format->value.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    private function competencyKind($competency): string
    {
        return (string) ($competency->curriculumEducationPlanReference?->competency_kind
            ?? $competency->educationPlanCompetency?->area?->kind
            ?? 'content');
    }

    private function competencyText($competency): string
    {
        $educationPlanCompetency = $competency->educationPlanCompetency
            ?? $competency->curriculumEducationPlanReference?->educationPlanCompetency;

        return (string) ($competency->local_wording
            ?: $educationPlanCompetency?->text
            ?: $educationPlanCompetency?->variants?->sortBy('position')->first()?->text
            ?: 'Kompetenz');
    }

    private function filename(TeachingGroup $group): string
    {
        $aktenzeichen = $this->filenamePart($group->aktenzeichen);
        $groupName = $this->filenamePart($group->name);
        $date = CarbonImmutable::now('Europe/Berlin')->format('Ymd');
        $filename = collect([$aktenzeichen, $groupName, $date])->filter()->implode('_').' Elternbrief';

        return $this->filenamePart($filename) ?: 'Elternbrief';
    }

    private function filenamePart(?string $value): string
    {
        return trim((string) preg_replace(['/[^\\pL\\pN._ -]+/u', '/\\s+/u', '/\.{2,}/'], ['-', ' ', '.'], (string) $value), ' .-_');
    }
}
