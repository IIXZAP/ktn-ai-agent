<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'แคมเปญ') · AI Sales Lead</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans+Thai:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">

    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-paper text-ink">

    <header class="border-b border-hairline bg-panel">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <a href="{{ route('campaigns.index') }}" class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 items-center justify-center rounded-md bg-ink text-panel">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <circle cx="8" cy="8" r="1.5" fill="currentColor"/>
                        <circle cx="8" cy="8" r="5" stroke="currentColor" stroke-width="1" opacity="0.5"/>
                        <line x1="8" y1="8" x2="8" y2="1.5" stroke="currentColor" stroke-width="1"/>
                    </svg>
                </span>
                <span class="font-display text-[15px] font-semibold tracking-tight">
                    สถานีตรวจจับลูกค้า
                </span>
            </a>

            <nav class="flex items-center gap-6 text-sm text-ink-soft">
                <a href="{{ route('campaigns.index') }}" class="hover:text-ink">แคมเปญ</a>
                @auth
                    <span class="font-mono text-xs">{{ auth()->user()->name }}</span>
                @endauth
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-6 py-10">
        @if (session('status'))
            <div class="mb-6 rounded-lg border border-signal-teal/30 bg-signal-teal/5 px-4 py-3 text-sm text-signal-teal">
                {{ session('status') }}
            </div>
        @endif

        @yield('content')
    </main>

</body>
</html>