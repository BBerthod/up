<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#09090b">

    <title>{{ config('app.name', 'Up by Radiank') }}</title>
    <meta name="description" content="Open-source uptime monitoring platform. Monitor your websites, APIs, and services with real-time alerts.">

    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ config('app.name', 'Up by Radiank') }}">
    <meta property="og:description" content="Open-source uptime monitoring platform. Monitor your websites, APIs, and services with real-time alerts.">
    <meta property="og:url" content="{{ config('app.url') }}">
    <meta property="og:image" content="{{ config('app.url') }}/icons/icon-512.png">

    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ config('app.name', 'Up by Radiank') }}">
    <meta name="twitter:description" content="Open-source uptime monitoring platform. Monitor your websites, APIs, and services with real-time alerts.">

    <link rel="icon" type="image/svg+xml" href="/icons/icon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
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
