<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ __('job_postings.preview.title', ['title' => '']) }}</title>
    @vite(['resources/css/app.css', 'resources/js/dynamic-form.js'])
</head>
<body class="bg-slate-50 font-sans text-slate-900 antialiased">
    <main class="px-4 py-4">
        {{ $slot }}
    </main>
</body>
</html>
