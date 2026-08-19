<?php

use App\Models\MaterialItem;
use App\Models\Organization;
use App\Models\ResourceLink;
use App\Models\ResourceReference;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the complete organization library and protects its CRUD actions', function () {
    Storage::fake('local');
    $organization = Organization::create(['name' => 'Bibliothek']);
    $otherOrganization = Organization::create(['name' => 'Andere Bibliothek']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $file = UploadedFile::fake()->create('Arbeitsblatt.pdf', 20, 'application/pdf');
    Storage::disk('local')->put('library/test.pdf', $file->getContent());
    $reference = ResourceReference::create(['organization_id' => $organization->id, 'original_name' => 'Arbeitsblatt.pdf', 'storage_path' => 'library/test.pdf', 'mime_type' => 'application/pdf', 'size' => 20]);
    ResourceLink::create(['organization_id' => $organization->id, 'title' => 'Religionspädagogik', 'url' => 'https://example.test/ru']);
    MaterialItem::create(['organization_id' => $organization->id, 'name' => 'Erzählkarten']);
    ResourceLink::create(['organization_id' => $otherOrganization->id, 'title' => 'Nicht sichtbar', 'url' => 'https://example.test/other']);

    $this->actingAs($user)->get('/ressourcen/bibliothek')->assertInertia(fn ($page) => $page->component('Resources/Library')->has('items', 3)->where('counts.resource', 1));
    $this->actingAs($user)->post('/ressourcen/bibliothek/ressourcen', ['title' => 'Neue Quelle', 'url' => 'https://example.test/new'])->assertRedirect();
    $this->actingAs($user)->get('/ressourcen/bibliothek?q=Erzählkarten&type=material')->assertInertia(fn ($page) => $page->has('items', 1));
    $this->actingAs($user)->get('/ressourcen/bibliothek/dateien/'.$reference->id.'/download')->assertOk();
    expect(ResourceLink::where('organization_id', $organization->id)->count())->toBe(2);
});
