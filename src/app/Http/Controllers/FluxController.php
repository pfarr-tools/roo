<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FluxController extends Controller
{
    public function credits(Request $request): JsonResponse
    {
        try {
            $response = Http::withHeaders($this->headers($this->apiKey($request)))->get($this->url('/credits'));
        } catch (ConnectionException $exception) {
            report($exception);

            return $this->unavailable();
        }

        return response()->json($response->json(), $response->status());
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'model' => ['required', 'string'], 'prompt' => ['required', 'string', 'max:10000'],
            'width' => ['required', 'integer', 'min:256', 'max:2048'], 'height' => ['required', 'integer', 'min:256', 'max:2048'],
            'count' => ['sometimes', 'integer', 'min:1', 'max:8'], 'prompt_upsampling' => ['sometimes', 'boolean'],
        ]);
        $model = collect(config('flux.models'))->firstWhere('key', $data['model']);
        abort_unless($model, 422, 'Das gewählte FLUX-Modell ist nicht verfügbar.');
        $payload = collect($data)->only(['prompt', 'width', 'height', 'prompt_upsampling'])->all();
        try {
            $response = Http::withHeaders($this->headers($this->apiKey($request)))->post($this->url('/'.$model['endpoint']), $payload);
        } catch (ConnectionException $exception) {
            report($exception);

            return $this->unavailable();
        }

        return response()->json($response->json(), $response->status());
    }

    public function poll(Request $request): JsonResponse
    {
        $key = $this->apiKey($request);
        $data = $request->validate(['url' => ['required', 'url']]);
        try {
            $response = Http::withHeaders($this->headers($key))->get($this->allowedUrl($data['url']));
            $payload = $response->json();
            if ($response->successful() && ($payload['status'] ?? null) === 'Ready' && filled($payload['result']['sample'] ?? null)) {
                $image = Http::withHeaders($this->headers($key))->get($this->allowedUrl($payload['result']['sample']));
                if ($image->successful()) {
                    $payload['image_data'] = 'data:'.($image->header('Content-Type') ?: 'image/png').';base64,'.base64_encode($image->body());
                }
            }
        } catch (ConnectionException $exception) {
            report($exception);

            return $this->unavailable();
        }

        return response()->json($payload, $response->status());
    }

    private function unavailable(): JsonResponse
    {
        return response()->json(['message' => 'Der FLUX-Dienst ist derzeit nicht erreichbar.'], 502);
    }

    private function apiKey(Request $request): string
    {
        $key = $request->user()?->flux_api_key;
        abort_unless(filled($key), 403, 'Kein FLUX API-Schlüssel hinterlegt.');

        return $key;
    }

    private function headers(string $key): array
    {
        return ['accept' => 'application/json', 'x-key' => $key, 'Content-Type' => 'application/json'];
    }

    private function url(string $path): string
    {
        return rtrim(config('flux.base_url'), '/').'/'.ltrim($path, '/');
    }

    private function allowedUrl(string $url): string
    {
        $parsed = parse_url($url);
        $host = (string) ($parsed['host'] ?? '');
        abort_unless(($parsed['scheme'] ?? '') === 'https' && ($host === 'api.bfl.ai' || str_ends_with($host, '.bfl.ai')), 422, 'Ungültige FLUX-Ergebnisadresse.');

        return $url;
    }
}
