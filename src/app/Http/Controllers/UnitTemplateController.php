<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUnitTemplateRequest;
use App\Models\UnitTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $templates = UnitTemplate::query()
            ->where('organization_id', auth()->user()->organization_id)
            ->where('is_active', true)
            ->when($query !== '', fn ($builder) => $builder->where(fn ($builder) => $builder->where('title', 'like', "%{$query}%")->orWhere('description', 'like', "%{$query}%")->orWhere('notes', 'like', "%{$query}%")))
            ->orderBy('title')
            ->get(['id', 'title', 'description', 'expected_hours', 'notes', 'version', 'copied_from_id']);

        return Inertia::render('UnitTemplates/Index', ['templates' => $templates, 'filters' => ['q' => $query]]);
    }

    public function store(StoreUnitTemplateRequest $request): RedirectResponse
    {
        UnitTemplate::create($request->validated() + [
            'organization_id' => $request->user()->organization_id,
            'version' => 1,
            'is_active' => true,
        ]);

        return to_route('unit-templates.index')->with('success', 'Unterrichtseinheit-Vorlage wurde angelegt.');
    }

    public function update(StoreUnitTemplateRequest $request, UnitTemplate $unitTemplate): RedirectResponse
    {
        $this->ensureVisible($unitTemplate);
        $unitTemplate->update($request->validated() + ['version' => $unitTemplate->version + 1]);

        return to_route('unit-templates.index')->with('success', 'Unterrichtseinheit-Vorlage wurde gespeichert.');
    }

    public function destroy(UnitTemplate $unitTemplate): RedirectResponse
    {
        $this->ensureVisible($unitTemplate);
        $unitTemplate->delete();

        return to_route('unit-templates.index')->with('success', 'Unterrichtseinheit-Vorlage wurde gelöscht.');
    }

    private function ensureVisible(UnitTemplate $unitTemplate): void
    {
        abort_unless($unitTemplate->organization_id === auth()->user()->organization_id && $unitTemplate->is_active, 403);
    }
}
