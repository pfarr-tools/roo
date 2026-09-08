@php
    $messages = [
        'Die Server machen gerade eine kurze Pausenaufsicht.',
        'Roo sortiert noch die Stundenpläne und sucht dabei den Kaffee.',
        'Ein Känguru hat den letzten Neustart beantragt. Wir prüfen das.',
        'Die Bits haben heute Konfetti statt Unterricht geplant.',
        'Der Hausmeister lädt gerade die Datenbank wieder auf.',
    ];
    $message = $messages[array_rand($messages)];
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wartungsmodus – Roo</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; display: grid; place-items: center; padding: 2rem; color: #3c250d; background: radial-gradient(circle at 15% 10%, #fff8e9 0, transparent 35%), linear-gradient(135deg, #f4eadb, #e9d8c1); }
        main { width: min(100%, 48rem); padding: clamp(2rem, 7vw, 5rem); text-align: center; background: rgba(255, 252, 246, .9); border: 1px solid rgba(112, 73, 34, .16); border-radius: 2rem; box-shadow: 0 1.5rem 4rem rgba(60, 37, 13, .16); }
        img { display: block; width: min(100%, 22rem); margin: 0 auto 2.5rem; }
        .eyebrow { margin: 0 0 .75rem; color: #9a5b1c; font-size: .8rem; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; }
        h1 { margin: 0; font-size: clamp(2.25rem, 7vw, 4.5rem); line-height: 1; letter-spacing: -.04em; }
        p { font-size: 1.1rem; line-height: 1.6; }
        .message { display: inline-block; margin: 1rem 0; padding: .9rem 1.2rem; color: #704921; background: #f9edd6; border-radius: 1rem; }
        .hint { margin-bottom: 0; color: #745f4c; font-size: .95rem; }
    </style>
</head>
<body>
    <main>
        <img src="{{ Vite::asset('resources/images/branding/roo-logo.png') }}" alt="Roo – Religionsunterricht organisieren">
        <p class="eyebrow">Kurz außer Betrieb</p>
        <h1>Wartungsmodus</h1>
        <p class="message">{{ $message }}</p>
        <p class="hint">Wir sind gleich wieder da. Deine Unterrichtsdaten bleiben natürlich, wo sie sind.</p>
    </main>
</body>
</html>
