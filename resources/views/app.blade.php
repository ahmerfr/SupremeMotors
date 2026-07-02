<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>{{ config('app.name', 'Laravel') }}</title>
        <link rel="icon" type="image/png" href="/assets/images/site-logo.png" />

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|saira:500,600,700,800,900|archivo:400,500,600,700|chivo-mono:400,500,700" rel="stylesheet" />

        @routes
        @vite(['resources/js/app.ts'])
        @inertiaHead

        <style>
            /* Critical styles to prevent flicker/distortion */
            #loading-overlay {
                position: fixed;
                inset: 0;
                background-color: white;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                z-index: 9999;
                transition: opacity 0.3s ease;
            }
        
            .loader-wrapper {
                padding: 1rem;
                border: 4px solid rgba(142, 37, 39, 0.2);
                border-radius: 9999px;
                box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
                animation: pulse 2s infinite;
            }
        
            .loader-logo {
                height: 80px;
                width: 80px;
                object-fit: contain;
                border-radius: 9999px;
                animation: spin 3s linear infinite;
            }
        
            .loader-text {
                margin-top: 1rem;
                color: #8e2527;
                font-weight: 600;
                font-size: 1.125rem;
            }
        
            @keyframes pulse {
                0%, 100% {
                    transform: scale(1);
                    opacity: 1;
                }
                50% {
                    transform: scale(1.05);
                    opacity: 0.7;
                }
            }
        
            @keyframes spin {
                0% {
                    transform: rotate(0deg);
                }
                100% {
                    transform: rotate(360deg);
                }
            }
        </style>
        
    </head>
    <body class="font-sans antialiased">
        <div id="loading-overlay">
            <div class="loader-wrapper">
                <img src="/assets/images/site-logo.png" alt="Site Logo" class="loader-logo" />
            </div>
            <p class="loader-text">Loading, please wait...</p>
        </div>

        @inertia
     
        <script>
            const hideOverlay = () => {
                const overlay = document.getElementById('loading-overlay');
                if (overlay) {
                    overlay.style.opacity = 0;
                    setTimeout(() => overlay.style.display = 'none', 300);
                }
            };
            window.addEventListener('load', hideOverlay);
            window.addEventListener('inertia:finish', hideOverlay);
        </script>
    </body>
</html>