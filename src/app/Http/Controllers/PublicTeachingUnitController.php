<?php

namespace App\Http\Controllers;

use App\Models\LessonGalleryImage;
use App\Models\ResourceReference;
use App\Models\TeachingUnit;
use App\Services\TeachingUnitPublicViewResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicTeachingUnitController extends Controller
{
    public function show(TeachingUnit $teachingUnit, TeachingUnitPublicViewResolver $resolver)
    {
        $view = $resolver->resolve($teachingUnit, CarbonImmutable::now('Europe/Berlin'));

        return response()->view('public.teaching-units.show', [
            'view' => $view,
        ]);
    }

    public function download(Request $request, TeachingUnit $teachingUnit, ResourceReference $resource, TeachingUnitPublicViewResolver $resolver)
    {
        abort_unless($resource->teaching_unit_id === $teachingUnit->id, 404);
        $view = $resolver->resolve($teachingUnit, CarbonImmutable::now('Europe/Berlin'));
        abort_unless($view->visiblePhaseResources->contains('id', $resource->id), 404);
        abort_unless(Storage::disk('local')->exists($resource->storage_path), 404);

        return Storage::disk('local')->download($resource->storage_path, $resource->original_name ?: 'Datei');
    }

    public function galleryImage(TeachingUnit $teachingUnit, LessonGalleryImage $galleryImage)
    {
        abort_unless($galleryImage->lesson()->where('teaching_unit_id', $teachingUnit->id)->exists(), 404);
        $resource = $galleryImage->resource;
        abort_unless($resource?->mime_type && str_starts_with($resource->mime_type, 'image/'), 404);
        abort_unless(Storage::disk('local')->exists($resource->storage_path), 404);

        return response()->file(Storage::disk('local')->path($resource->storage_path), [
            'Content-Type' => $resource->mime_type,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
