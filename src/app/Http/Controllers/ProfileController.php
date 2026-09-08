<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\URL;
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
                'public_phone' => $user->public_phone,
            ],
            'integrations' => [
                'openai' => filled($user->openai_api_key),
                'flux' => filled($user->flux_api_key),
            ],
            'calendarUrl' => URL::signedRoute('calendar.lessons', ['user' => $user->id]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($request->user()->id)],
            'public_phone' => ['nullable', 'string', 'max:50'],
            'openai_api_key' => ['nullable', 'string', 'max:10000'],
            'flux_api_key' => ['nullable', 'string', 'max:10000'],
        ])->validate();

        $user = $request->user();
        $attributes = ['name' => $validated['name'], 'email' => $validated['email'], 'public_phone' => $validated['public_phone'] ?? null];

        foreach (['openai_api_key', 'flux_api_key'] as $key) {
            if (filled($validated[$key] ?? null)) {
                $attributes[$key] = $validated[$key];
            }
        }

        $user->forceFill($attributes)->save();

        return back()->with('success', 'Das Profil wurde gespeichert.');
    }
}
