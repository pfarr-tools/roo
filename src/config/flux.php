<?php

return [
    'base_url' => env('FLUX_API_BASE_URL', 'https://api.bfl.ai/v1'),
    'models' => [
        ['key' => 'flux2-pro-preview', 'label' => 'FLUX.2 [pro] Preview', 'endpoint' => 'flux-2-pro-preview', 'prompt_upsampling' => true],
        ['key' => 'flux2-pro', 'label' => 'FLUX.2 [pro]', 'endpoint' => 'flux-2-pro', 'prompt_upsampling' => true],
        ['key' => 'flux2-max', 'label' => 'FLUX.2 [max]', 'endpoint' => 'flux-2-max', 'prompt_upsampling' => true],
        ['key' => 'flux2-flex', 'label' => 'FLUX.2 [flex]', 'endpoint' => 'flux-2-flex', 'prompt_upsampling' => true],
        ['key' => 'flux2-klein-9b', 'label' => 'FLUX.2 [klein] 9B', 'endpoint' => 'flux-2-klein-9b', 'prompt_upsampling' => false],
        ['key' => 'flux2-klein-9b-preview', 'label' => 'FLUX.2 [klein] 9B Preview', 'endpoint' => 'flux-2-klein-9b-preview', 'prompt_upsampling' => false],
        ['key' => 'flux2-klein-4b', 'label' => 'FLUX.2 [klein] 4B', 'endpoint' => 'flux-2-klein-4b', 'prompt_upsampling' => false],
    ],
];
