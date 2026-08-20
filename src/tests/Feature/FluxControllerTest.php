<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('proxyfiziert Credits, Bildauftrag und asynchrones FLUX-Ergebnis mit dem Benutzerschlüssel', function () {
    $organization = Organization::create(['name' => 'Flux Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id, 'flux_api_key' => 'secret-key']);
    Http::fake([
        'https://api.bfl.ai/v1/credits' => Http::response(['credits' => 42], 200),
        'https://api.bfl.ai/v1/flux-2-flex' => Http::response(['polling_url' => 'https://api.bfl.ai/v1/get_result?id=abc', 'cost' => 3], 200),
        'https://api.bfl.ai/v1/get_result?id=abc' => Http::response(['status' => 'Ready', 'result' => ['sample' => 'https://cdn.bfl.ai/result.png']], 200),
        'https://cdn.bfl.ai/result.png' => Http::response('png-data', 200, ['Content-Type' => 'image/png']),
    ]);

    $this->actingAs($user)->get('/flux/credits')->assertOk()->assertJson(['credits' => 42]);
    $this->actingAs($user)->postJson('/flux/generate', ['model' => 'flux2-flex', 'prompt' => 'Eine Linie', 'width' => 1024, 'height' => 1024])->assertOk()->assertJsonPath('cost', 3);
    $this->actingAs($user)->get('/flux/poll?url='.urlencode('https://api.bfl.ai/v1/get_result?id=abc'))->assertOk()->assertJsonPath('status', 'Ready')->assertJsonPath('image_data', 'data:image/png;base64,cG5nLWRhdGE=');

    Http::assertSent(fn ($request) => $request->hasHeader('x-key', 'secret-key'));
});
