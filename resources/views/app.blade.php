<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite('resources/js/app.js')
</head>
<body class="antialiased">
@inertia
<div>This is loading, from app.blade.php</div>
</body>
</html>
