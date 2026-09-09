<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\LessonGalleryImage;
use App\Models\ResourceReference;
use App\Models\TeachingGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LessonGalleryController extends Controller
{
    public function store(Request $request, TeachingGroup $teachingGroup, Lesson $lesson): RedirectResponse
    {
        $this->authorizeLesson($request, $teachingGroup, $lesson);
        $data = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:30'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:20480'],
        ]);

        DB::transaction(function () use ($data, $lesson, $request): void {
            $position = (int) ($lesson->galleryImages()->max('position') ?? -1) + 1;
            foreach ($data['images'] as $image) {
                $extension = strtolower($image->getClientOriginalExtension() ?: $image->extension() ?: 'jpg');
                $path = $image->storeAs(
                    'lessons/'.$lesson->id.'/gallery',
                    Str::uuid().'.'.$extension,
                    'local',
                );
                $resource = ResourceReference::create([
                    'user_id' => $request->user()->id,
                    'original_name' => $image->getClientOriginalName(),
                    'storage_path' => $path,
                    'mime_type' => $image->getMimeType(),
                    'size' => $image->getSize(),
                    'checksum' => hash_file('sha256', $image->getRealPath()),
                    'security_status' => 'pending',
                    'source' => 'lesson_gallery',
                    'version' => 1,
                ]);
                $lesson->galleryImages()->create([
                    'resource_reference_id' => $resource->id,
                    'position' => $position++,
                ]);
            }
        });

        return back()->with('success', 'Bilder wurden zur Galerie hinzugefügt.');
    }

    public function destroy(Request $request, TeachingGroup $teachingGroup, Lesson $lesson, LessonGalleryImage $galleryImage): RedirectResponse|JsonResponse
    {
        $this->authorizeLesson($request, $teachingGroup, $lesson);
        abort_unless($galleryImage->lesson_id === $lesson->id, 404);

        $resource = $galleryImage->resource;
        Storage::disk('local')->delete($resource?->storage_path);
        $galleryImage->delete();
        $resource?->delete();

        $message = 'Bild wurde aus der Galerie entfernt.';
        return $request->expectsJson() ? response()->json(['message' => $message, 'gallery_image_id' => $galleryImage->id]) : back()->with('success', $message);
    }

    private function authorizeLesson(Request $request, TeachingGroup $teachingGroup, Lesson $lesson): void
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($lesson->unit()->where('teaching_group_id', $teachingGroup->id)->where('user_id', $request->user()->id)->exists(), 404);
    }
}
