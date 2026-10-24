<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Cardioprenatal')</title>
    @include('partials.favicon')

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/noty/lib/noty.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/noty/lib/themes/mint.css">
    <script src="https://cdn.jsdelivr.net/npm/noty/lib/noty.min.js"></script>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/js/app.js'])

    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap');

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html {
            color-scheme: light;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: #fdf5f5;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow-x: clip;
            position: relative;
        }

        /* Gradiente de fundo */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 75% -10%, rgba(192,57,43,.15) 0%, transparent 70%),
                radial-gradient(ellipse 60% 50% at -10% 85%, rgba(127,12,26,.12) 0%, transparent 65%);
            pointer-events: none;
            z-index: 0;
        }

        /* Linha de ECG decorativa */
        .ecg-bg {
            position: fixed;
            bottom: 18%;
            left: 0;
            width: 100%;
            opacity: .05;
            pointer-events: none;
            z-index: 0;
        }

        /* Círculos decorativos */
        .deco-circle {
            position: fixed;
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }
        .deco-circle-1 {
            width: 380px; height: 380px;
            border: 1.5px solid rgba(192,57,43,.13);
            top: -120px; right: -90px;
        }
        .deco-circle-2 {
            width: 220px; height: 220px;
            border: 1.5px solid rgba(127,12,26,.09);
            bottom: 50px; left: -70px;
        }
        .deco-circle-3 {
            width: 100px; height: 100px;
            background: rgba(231,76,60,.06);
            top: 35%; left: 4%;
        }

        /* Wrapper do conteúdo */
        .auth-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            min-height: 100dvh;
            padding: max(24px, env(safe-area-inset-top)) max(16px, env(safe-area-inset-right)) max(24px, env(safe-area-inset-bottom)) max(16px, env(safe-area-inset-left));
        }
                .auth-footer {
            position: relative;
            z-index: 1;
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: rgba(127,12,26,.4);
            font-family: 'DM Sans', sans-serif;
            letter-spacing: .02em;
        }

        .auth-footer span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .auth-footer svg {
            width: 11px; height: 11px;
            color: rgba(192,57,43,.5);
        }

        .auth-footer-logo {
            display: inline-flex;
            width: 28px;
            height: 28px;
            overflow: hidden;
            border-radius: 6px;
            background: #fff;
            box-shadow: 0 0 0 1px rgba(127, 12, 26, 0.12);
        }
        .auth-footer-logo img {
            transform: scale(1.72);
            transform-origin: center 28%;
        }
    </style>
</head>

<body>

    <!-- Decorações de fundo -->
    <div class="deco-circle deco-circle-1"></div>
    <div class="deco-circle deco-circle-2"></div>
    <div class="deco-circle deco-circle-3"></div>

    <!-- ECG decorativo -->
    <svg class="ecg-bg" viewBox="0 0 1440 120" fill="none" xmlns="http://www.w3.org/2000/svg">
        <polyline
            points="0,60 180,60 210,60 225,20 240,100 255,10 270,110 285,60 310,60 420,60 435,60 450,20 465,100 480,10 495,110 510,60 530,60 640,60 655,60 670,20 685,100 700,10 715,110 730,60 750,60 860,60 875,60 890,20 905,100 920,10 935,110 950,60 970,60 1080,60 1095,60 1110,20 1125,100 1140,10 1155,110 1170,60 1190,60 1300,60 1315,60 1330,20 1345,100 1360,10 1375,110 1390,60 1440,60"
            stroke="#c0392b" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"
        />
    </svg>

    <div class="auth-wrapper">
        @yield('content')
        
        <div class="auth-footer">
            <span style="display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; justify-content: center;">
                <span class="auth-footer-logo">
                    @include('partials.brand-logo', ['variant' => 'footer', 'decorative' => true])
                </span>
                Cardioprenatal. Rastreio de cardiopatias congênitas em gestantes diabéticas. © {{ date('Y') }}
            </span>
        </div>
    </div>

</body>

</html>