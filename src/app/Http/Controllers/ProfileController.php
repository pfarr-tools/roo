<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Show', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'integrations' => [
                'openai' => filled($user->openai_api_key),
                'flux' => filled($user->flux_api_key),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($request->user()->id)],
            'openai_api_key' => ['nullable', 'string', 'max:10000'],
            'flux_api_key' => ['nullable', 'string', 'max:10000'],
        ])->validate();

        $user = $request->user();
        $attributes = ['name' => $validated['name'], 'email' => $validated['email']];

        foreach (['openai_api_key', 'flux_api_key'] as $key) {
            if (filled($validated[$key] ?? null)) {
                $attributes[$key] = $validated[$key];
            }
        }

        $user->forceFill($attributes)->save();

        return back()->with('success', 'Das Profil wurde gespeichert.');
    }
}
