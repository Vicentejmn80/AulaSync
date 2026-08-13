<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'AulaSync') }} · Onboarding</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.nova-theme')
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg-primary);
            color: var(--text-primary);
        }
    </style>
    @stack('styles')
</head>
<body>
    {{ $slot }}
    @stack('scripts')
</body>
</html>
