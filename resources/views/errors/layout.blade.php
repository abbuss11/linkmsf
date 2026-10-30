<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; color: #0f172a; }
        main { max-width: 32rem; margin: 15vh auto 0; padding: 0 1rem; text-align: center; }
        h1 { font-size: 1.25rem; } p { color: #334155; } a { color: #0f766e; }
    </style>
</head>
<body>
    <main>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <p><a href="{{ url('/') }}">Retour à l’accueil</a></p>
    </main>
</body>
</html>
