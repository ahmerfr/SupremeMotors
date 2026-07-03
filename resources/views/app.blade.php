<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>{{ config('app.name', 'Laravel') }}</title>
        <link rel="icon" type="image/png" href="/assets/images/site-logo.png" />

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|manrope:400,500,600,700,800|archivo:400,500,600,700,800,900|chivo-mono:400,500,700" rel="stylesheet" />

        @routes
        @vite(['resources/js/app.ts'])
        @inertiaHead

        <style>
            /* Critical styles to prevent flicker/distortion */
            #loading-overlay {
                position: fixed;
                inset: 0;
                background: #fff;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                z-index: 9999;
                transition: opacity 0.4s ease;
            }

            .loader-logo {
                height: 62px;
                width: auto;
                object-fit: contain;
                animation: loader-in 0.6s cubic-bezier(0.2, 0.7, 0.2, 1) both;
            }

            .loader-track {
                position: relative;
                width: 210px;
                height: 3px;
                border-radius: 100px;
                background: #eef1f6;
                overflow: hidden;
                margin-top: 30px;
                animation: loader-in 0.6s 0.12s cubic-bezier(0.2, 0.7, 0.2, 1) both;
            }

            .loader-bar {
                position: absolute;
                top: 0;
                left: 0;
                height: 100%;
                width: 40%;
                border-radius: 100px;
                background: linear-gradient(90deg, #e5262d, #c8151c);
                animation: loader-sweep 1.1s cubic-bezier(0.45, 0, 0.4, 1) infinite;
            }

            @keyframes loader-sweep {
                0% { left: -40%; }
                100% { left: 100%; }
            }

            @keyframes loader-in {
                from { opacity: 0; transform: translateY(10px) scale(0.97); }
                to { opacity: 1; transform: none; }
            }

            @media (prefers-reduced-motion: reduce) {
                .loader-logo, .loader-track, .loader-bar { animation: none; }
            }
        </style>
        
    </head>
    <body class="font-sans antialiased">
        <div id="loading-overlay">
            <img src="/assets/images/site-logo.png" alt="Supreme Motors Ltd" class="loader-logo" />
            <div class="loader-track"><div class="loader-bar"></div></div>
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