<?php

it('renders a German maintenance page with the Roo logo and a funny message', function () {
    $html = view('errors.503')->render();

    expect($html)
        ->toContain('Wartungsmodus')
        ->toContain('roo-logo.png')
        ->toMatch('/Kaffee|Unterricht|Pausenaufsicht|Hausmeister|Känguru|Konfetti/u');
});
