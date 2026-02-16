<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>
<meta name="description" content="A personal finance app to manage your conscious spending plan, track net worth, and categorize expenses.">

<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#10b981">

{{-- Preload primary Latin fonts so they download in parallel with CSS --}}
<link rel="preload" href="{{ Vite::asset('resources/fonts/instrument-sans-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="{{ Vite::asset('resources/fonts/instrument-sans-latin-600-normal.woff2') }}" as="font" type="font/woff2" crossorigin>

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
