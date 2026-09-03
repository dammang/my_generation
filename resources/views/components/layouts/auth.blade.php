<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- A reset link is a one-time address with a token in it; keep it out of
         referrers and out of search results. --}}
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ $title ?? 'Reset your password' }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface text-ink dark:bg-surface-dark dark:text-ink-dark font-sans flex items-center justify-center p-6">
    <main class="w-full max-w-md flex flex-col gap-8">
        <header class="flex flex-col items-center gap-4 text-center">
            {{-- The same three-node mark the app opens with. --}}
            <svg viewBox="0 0 64 56" class="w-14 h-12 text-brand dark:text-brand-soft" fill="none" aria-hidden="true">
                <path d="M32 10v8M32 18H14v10M32 18h18v10M14 28l-6 12M50 28l6 12"
                      stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="32" cy="7" r="5" fill="currentColor"/>
                <circle cx="14" cy="29" r="5" fill="currentColor"/>
                <circle cx="50" cy="29" r="5" fill="currentColor"/>
                <circle cx="7" cy="44" r="5" fill="currentColor"/>
                <circle cx="57" cy="44" r="5" fill="currentColor"/>
            </svg>
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-tight">{{ $heading ?? 'Choose a new password' }}</h1>
                @isset($subheading)
                    <p class="text-sm text-ink/70 dark:text-ink-dark/70">{{ $subheading }}</p>
                @endisset
            </div>
        </header>

        {{ $slot }}

        <footer class="text-center text-xs text-ink/50 dark:text-ink-dark/50">
            {{ config('app.name') }}
        </footer>
    </main>
</body>
</html>
