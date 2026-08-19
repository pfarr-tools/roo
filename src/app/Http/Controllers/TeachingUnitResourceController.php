<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadUnitTemplateResourceRequest;
use App\Models\ResourceReference;
use App\Models\TeachingGroup;
use App\Models\TeachingUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TeachingUnitResourceController extends Controller
{
    public function store(UploadUnitTemplateResourceRequest $request, TeachingGroup $teachingGroup, TeachingUnit $teachingUnit): RedirectResponse
    {
        $this->authorizeUnit($request, $teachingGroup, $teachingUnit);
        $file = $request->file('resource');
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::slug($teachingUnit->title ?: 'unterrichtseinheit').'-'.Str::uuid().($extension ? '.'.$extension : '');
        $path = $file->storeAs('teaching-units/'.$teachingUnit->id, $filename, 'local');
        $teachingUnit->resources()->create([
            'organization_id' => $request->user()->organization_id,
            'original_name' => $file->getClientOriginalName(),
            'description' => $request->input('description'),
            'storage_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'security_status' => 'pending',
            'source' => 'user_upload',
            'version' => 1,
        ]);

        return back()->with('success', 'Anhang wurde hochgeladen.');
    }

    public function update(Request $request, TeachingGroup $teachingGroup, TeachingUnit $teachingUnit, ResourceReference $resource): RedirectResponse
    {
        $this->authorizeUnit($request, $teachingGroup, $teachingUnit);
        abort_unless($resource->teaching_unit_id === $teachingUnit->id, 404);
        $resource->update($request->validate(['description' => ['nullable', 'string', 'max:1000']]));

        return back()->with('success', 'Beschreibung des Anhangs wurde gespeichert.');
    }

    public function download(Request $request, TeachingGroup $teachingGroup, TeachingUnit $teachingUnit, ResourceReference $resource)
    {
        $this->authorizeUnit($request, $teachingGroup, $teachingUnit);
        abort_unless($resource->teaching_unit_id === $teachingUnit->id, 404);

        return Storage::disk('local')->download($resource->storage_path, $this->filenameFor($teachingUnit, $resource));
    }

    public function preview(Request $request, TeachingGroup $teachingGroup, TeachingUnit $teachingUnit, ResourceReference $resource)
    {
        $this->authorizeUnit($request, $teachingGroup, $teachingUnit);
        abort_unless($resource->teaching_unit_id === $teachingUnit->id, 404);

        return response()->file(Storage::disk('local')->path($resource->storage_path), ['Content-Type' => $resource->mime_type ?: 'application/octet-stream']);
    }

    public function destroy(Request $request, TeachingGroup $teachingGroup, TeachingUnit $teachingUnit, ResourceReference $resource): RedirectResponse
    {
        $this->authorizeUnit($request, $teachingGroup, $teachingUnit);
        abort_unless($resource->teaching_unit_id === $teachingUnit->id, 404);
        Storage::disk('local')->delete($resource->storage_path);
        $resource->delete();

        return back()->with('success', 'Anhang wurde gelöscht.');
    }

    private function authorizeUnit(Request $request, TeachingGroup $teachingGroup, TeachingUnit $teachingUnit): void
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($teachingUnit->teaching_group_id === $teachingGroup->id && $teachingUnit->organization_id === $request->user()->organization_id, 404);
    }

    private function filenameFor(TeachingUnit $teachingUnit, ResourceReference $resource): string
    {
        $group = $teachingUnit->group()->with(['gradeLevels', 'schoolYear'])->first();
        $grade = $this->filenamePart($group?->gradeLevels->pluck('grade_level')->implode('-') ?: '');
        $aktenzeichen = $this->filenamePart($group?->aktenzeichen ?: '');
        $keyword = $this->filenamePart($teachingUnit->keyword ?: '');
        $original = $this->filenamePart($resource->original_name ?: 'Datei');
        $prefix = $aktenzeichen !== '' ? $aktenzeichen.'_'.$grade : $grade;

        return trim(collect([$prefix, $keyword, $original])->filter()->implode(' '));
    }

    private function filenamePart(string $value): string
    {
        return trim((string) preg_replace(['/[^\pL\pN._ -]+/u', '/\s+/u', '/\.{2,}/'], ['-', ' ', '.'], $value), " .-");
    }
}
