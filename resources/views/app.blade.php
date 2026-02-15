<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0a0f1a">

    <title>{{ config('app.name', 'Up') }}</title>
    <link rel="manifest" href="/manifest.json">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    @routes
    @inertiaHead
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body class="antialiased">
    @inertia
</body>
</html>
