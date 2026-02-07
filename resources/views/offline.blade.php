<!DOCTYPE html>
<html lang="en" class="dark">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="theme-color" content="#10b981" />
        <title>Offline - Money Manager</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(to bottom, #0a0a0a, #171717);
                color: #fafafa;
                font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            }
            .container {
                text-align: center;
                padding: 2rem;
            }
            .logo {
                width: 4rem;
                height: 4rem;
                margin: 0 auto 1.5rem;
            }
            h1 {
                font-size: 1.5rem;
                font-weight: 600;
                margin-bottom: 0.5rem;
            }
            p {
                color: #a1a1aa;
                margin-bottom: 1.5rem;
            }
            button {
                background: #10b981;
                color: white;
                border: none;
                padding: 0.5rem 1.25rem;
                border-radius: 0.5rem;
                font-size: 0.875rem;
                font-weight: 500;
                cursor: pointer;
            }
            button:hover { background: #059669; }
        </style>
    </head>
    <body>
        <div class="container">
            <svg class="logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40">
                <ellipse cx="20" cy="32" rx="14" ry="5" fill="currentColor" opacity="0.3"/>
                <ellipse cx="20" cy="27" rx="14" ry="5" fill="currentColor" opacity="0.5"/>
                <ellipse cx="20" cy="22" rx="14" ry="5" fill="currentColor" opacity="0.7"/>
                <ellipse cx="20" cy="17" rx="14" ry="5" fill="currentColor"/>
                <path d="M20 10v-6M16 5.5l4-3.5 4 3.5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <h1>You're offline</h1>
            <p>Check your connection and try again.</p>
            <button onclick="location.reload()">Retry</button>
        </div>
    </body>
</html>
