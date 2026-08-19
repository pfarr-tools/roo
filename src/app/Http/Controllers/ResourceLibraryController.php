<?php

namespace App\Http\Controllers;

use App\Models\MaterialItem;
use App\Models\ResourceLink;
use App\Models\ResourceReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceLibraryController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', 'all');
        $organizationId = $request->user()->organization_id;
        $matches = collect();

        if ($type === 'all' || $type === 'file') {
            $matches = $matches->concat(ResourceReference::where('organization_id', $organizationId)->when($query !== '', fn ($builder) => $builder->where('original_name', 'like', "%{$query}%"))->orderBy('original_name')->limit(30)->get(['id', 'original_name', 'description', 'mime_type', 'size', 'page_count'])->map(fn ($item) => $item->setAttribute('kind', 'file')));
        }
        if ($type === 'all' || $type === 'resource') {
            $matches = $matches->concat(ResourceLink::where('organization_id', $organizationId)->when($query !== '', fn ($builder) => $builder->where(fn ($nested) => $nested->where('title', 'like', "%{$query}%")->orWhere('url', 'like', "%{$query}%")))->orderBy('title')->limit(30)->get(['id', 'title', 'url'])->map(fn ($item) => $item->setAttribute('kind', 'resource')));
        }
        if ($type === 'all' || $type === 'material') {
            $matches = $matches->concat(MaterialItem::where('organization_id', $organizationId)->when($query !== '', fn ($builder) => $builder->where(fn ($nested) => $nested->where('name', 'like', "%{$query}%")->orWhere('material_number', 'like', "%{$query}%")->orWhere('storage_location', 'like', "%{$query}%")))->orderBy('name')->limit(30)->get(['id', 'name', 'material_number', 'storage_location', 'description'])->map(fn ($item) => $item->setAttribute('kind', 'material')));
        }

        return response()->json($matches->values());
    }
}
