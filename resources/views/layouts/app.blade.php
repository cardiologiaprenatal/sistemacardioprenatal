<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Cardioprenatal')</title>
    @include('partials.favicon')

    @vite(['resources/js/app.js'])

    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap');

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html {
            -webkit-text-size-adjust: 100%;
            text-size-adjust: 100%;
        }

        :root {
            --primary:      #7f0c1a;
            --accent:       #c0392b;
            --accent-mid:   #e74c3c;
            --accent-light: #fdf0f0;
            --success:      #1e7a45;
            --text:         #1c1a1a;
            --muted:        #6b5a5a;
            --border:       #f0d5d5;
            --surface:      #ffffff;
            --bg:           #faf4f4;
            --nav-bg:       #7f0c1a;

            /* Semântica clínica (risco) — separada da marca */
            --risk-low:     #1e7a45;
            --risk-low-bg:  rgba(30, 122, 69, 0.12);
            --risk-low-border: rgba(30, 122, 69, 0.28);
            --risk-mid:     #b86e00;
            --risk-mid-bg:  rgba(184, 110, 0, 0.14);
            --risk-mid-border: rgba(184, 110, 0, 0.32);
            --risk-high:    #b71c1c;
            --risk-high-bg: rgba(183, 28, 28, 0.12);
            --risk-high-border: rgba(183, 28, 28, 0.30);

            /* Status de sistema (não confundir com risco) */
            --status-info:        #1a5f7a;
            --status-info-bg:     rgba(26, 95, 122, 0.08);
            --status-info-border: rgba(26, 95, 122, 0.22);
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            color: var(--text);
            position: relative;
            overflow-x: clip;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 55% at 100% 0%, rgba(192, 57, 43, 0.08) 0%, transparent 55%),
                radial-gradient(ellipse 55% 45% at 0% 100%, rgba(127, 12, 26, 0.06) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        body > header,
        body > main,
        body > footer.app-site-footer {
            position: relative;
            z-index: 1;
        }

        /* ── HEADER ── */
        header {
            background: linear-gradient(135deg, var(--primary) 0%, #a01020 100%);
            box-shadow: 0 2px 16px rgba(127,12,26,.25);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 max(16px, env(safe-area-inset-right)) 0 max(16px, env(safe-area-inset-left));
            min-height: 56px;
            height: auto;
            padding-top: max(0px, env(safe-area-inset-top));
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        /* Logo */
        .header-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            min-width: 0;
            flex: 0 1 auto;
            max-width: min(280px, 36vw);
        }

        .header-logo-mark {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            padding: 0;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.14);
        }
        .header-logo-mark .brand-logo--header {
            width: 100%;
            height: 100%;
            max-width: none;
            object-fit: cover;
            object-position: center 22%;
            transform: scale(1.72);
            transform-origin: center 28%;
        }

        .header-logo-glyphs {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .header-logo-icon svg {
            flex-shrink: 0;
            color: #fff;
            filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.12));
        }

        .header-logo-icon .heart-brand {
            width: 21px;
            height: 21px;
        }

        .header-logo-icon .icon-baby-brand {
            width: 20px;
            height: 20px;
            opacity: 0.98;
        }

        /* Pulso suave — eco do “batimento” (tema cardiológico / CHD) */
        .heart-brand {
            transform-origin: center;
            animation: heartPulse 2.4s ease-in-out infinite;
        }

        @keyframes heartPulse {
            0%, 100% { transform: scale(1); }
            12% { transform: scale(1.12); }
            24% { transform: scale(1); }
            36% { transform: scale(1.08); }
            48% { transform: scale(1); }
        }

        @media (prefers-reduced-motion: reduce) {
            .heart-brand { animation: none; }
        }

        .header-logo-text {
            font-family: 'DM Serif Display', serif;
            font-size: clamp(16px, 2.2vw, 21px);
            color: #fff;
            letter-spacing: -0.02em;
            font-weight: 400;
            line-height: 1.15;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .header-logo-tagline {
            display: block;
            font-family: 'DM Sans', sans-serif;
            font-size: clamp(8px, 1.35vw, 10px);
            font-weight: 500;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: rgba(255,255,255,.72);
            margin-top: 2px;
            line-height: 1.35;
            max-width: 100%;
            overflow-wrap: anywhere;
            word-break: break-word;
            hyphens: auto;
        }

        .header-logo-tagline .tagline-short {
            display: none;
        }

        .header-logo-text-wrap {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            min-width: 0;
            flex: 1 1 auto;
            overflow: hidden;
        }

        /* Nav desktop */
        .nav-desktop {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex: 1 1 auto;
            min-width: 0;
            gap: 2px;
            flex-wrap: nowrap;
        }

        .nav-desktop .nav-link {
            flex: 0 0 auto;
            white-space: nowrap;
            padding: 8px 10px;
            font-size: 13.5px;
            gap: 5px;
            border-radius: 9px;
        }

        .nav-desktop .nav-link svg {
            width: 15px;
            height: 15px;
            flex-shrink: 0;
        }

        .nav-desktop .btn-logout {
            flex: 0 0 auto;
            white-space: nowrap;
            padding: 8px 12px;
            font-size: 13.5px;
            border-radius: 9px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            color: rgba(255,255,255,.8);
            text-decoration: none;
            transition: background .18s, color .18s;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,.15);
            color: #fff;
        }

        .nav-link svg { width: 16px; height: 16px; }
        .nav-icon-baby { flex-shrink: 0; }

        /* Separador vertical */
        .nav-sep {
            width: 1px;
            height: 20px;
            background: rgba(255,255,255,.2);
            margin: 0 4px;
            flex-shrink: 0;
        }

        /* Botão sair */
        .btn-logout {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            color: rgba(255,255,255,.8);
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.15);
            cursor: pointer;
            transition: background .18s, color .18s;
            font-family: 'DM Sans', sans-serif;
        }

        .btn-logout:hover {
            background: rgba(255,255,255,.18);
            color: #fff;
        }

        .btn-logout svg { width: 15px; height: 15px; }

        /* Botão mobile */
        .btn-mobile-menu {
            display: none;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 10px;
            min-width: 44px;
            min-height: 44px;
            padding: 10px;
            cursor: pointer;
            color: #fff;
            transition: background .18s ease;
            -webkit-tap-highlight-color: transparent;
        }

        .btn-mobile-menu:hover,
        .btn-mobile-menu[aria-expanded="true"] {
            background: rgba(255,255,255,.22);
        }

        .btn-mobile-menu svg { width: 22px; height: 22px; display: block; }

        .btn-mobile-menu .icon-close { display: none; }
        .btn-mobile-menu[aria-expanded="true"] .icon-open { display: none; }
        .btn-mobile-menu[aria-expanded="true"] .icon-close { display: block; }

        /* Overlay do menu mobile (fora do header, cobre o conteúdo) */
        .nav-mobile-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 95;
            background: rgba(28, 26, 26, 0.42);
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
        }

        .nav-mobile-overlay.is-open {
            display: block;
        }

        /* Menu mobile */
        .nav-mobile {
            display: none;
            flex-direction: column;
            gap: 6px;
            background: linear-gradient(180deg, #8a1020 0%, var(--primary) 100%);
            border-top: 1px solid rgba(255,255,255,.12);
            padding: 12px max(16px, env(safe-area-inset-right)) max(16px, env(safe-area-inset-bottom)) max(16px, env(safe-area-inset-left));
        }

        .nav-mobile.open {
            display: flex;
            max-height: min(78vh, calc(100dvh - 56px - env(safe-area-inset-top, 0px)));
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
            padding-bottom: max(20px, env(safe-area-inset-bottom));
        }

        .nav-mobile .nav-link {
            justify-content: flex-start;
            width: 100%;
            min-height: 48px;
            padding: 12px 14px;
            font-size: 15px;
            border-radius: 12px;
            gap: 10px;
        }

        .nav-mobile .nav-link.active {
            background: rgba(255,255,255,.2);
            color: #fff;
            font-weight: 600;
        }

        .nav-mobile .btn-logout {
            width: 100%;
            justify-content: center;
            min-height: 48px;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 15px;
        }

        .nav-mobile-sep {
            height: 1px;
            background: rgba(255,255,255,.14);
            margin: 6px 0;
            flex-shrink: 0;
        }

        body.nav-mobile-open {
            overflow: hidden;
            touch-action: none;
        }

        /* ── MAIN ── */
        main {
            flex: 1;
            max-width: 1280px;
            width: 100%;
            min-width: 0;
            margin: 0 auto;
            padding: 32px max(16px, env(safe-area-inset-right)) 32px max(16px, env(safe-area-inset-left));
        }

        /* ── FOOTER ── */
        footer {
            background: var(--surface);
            border-top: 1px solid var(--border);
            padding: 14px max(16px, env(safe-area-inset-right)) max(14px, env(safe-area-inset-bottom)) max(16px, env(safe-area-inset-left));
            text-align: center;
            font-size: clamp(12px, 2.4vw, 13px);
            color: var(--muted);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 6px;
            row-gap: 8px;
        }

        footer > span:last-child {
            max-width: 100%;
            overflow-wrap: anywhere;
            word-break: break-word;
            line-height: 1.45;
        }

        .footer-brand-icons {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            margin-right: 2px;
            overflow: hidden;
            border-radius: 6px;
            background: #fff;
            box-shadow: 0 0 0 1px var(--border);
        }

        .footer-brand-icons img {
            display: block;
            transform: scale(1.72);
            transform-origin: center 28%;
        }

        .footer-brand-icons svg {
            width: 13px;
            height: 13px;
            color: var(--accent);
            flex-shrink: 0;
        }

        /* Desktop largo: mais respiro e rótulos completos */
        @media (min-width: 1400px) {
            .nav-desktop { gap: 4px; }
            .nav-desktop .nav-link {
                padding: 8px 12px;
                font-size: 14px;
                gap: 6px;
            }
            .nav-desktop .btn-logout {
                padding: 8px 14px;
                font-size: 14px;
            }
            .header-logo { max-width: none; }
        }

        /* Notebook / desktop médio: esconde ícones e usa rótulos curtos */
        @media (min-width: 1200px) and (max-width: 1399px) {
            .nav-desktop .nav-link svg,
            .nav-desktop .btn-logout svg {
                display: none;
            }
            .nav-desktop .nav-link {
                padding: 8px 9px;
                font-size: 13px;
            }
            .header-logo { max-width: 220px; }
            .header-logo-tagline {
                letter-spacing: 0.04em;
            }
            .header-logo-tagline .tagline-full { display: none; }
            .header-logo-tagline .tagline-short { display: inline; }
        }

        .nav-label-short { display: none; }

        @media (min-width: 1200px) and (max-width: 1399px) {
            .nav-desktop .nav-label-full { display: none; }
            .nav-desktop .nav-label-short { display: inline; }
        }

        /* Abaixo de 1200px: hamburger (evita nav espremida no desktop estreito) */
        @media (max-width: 1199px) {
            .nav-desktop { display: none; }
            .btn-mobile-menu { display: inline-flex; }
            .header-inner {
                min-height: 58px;
                padding-top: max(4px, env(safe-area-inset-top));
                padding-bottom: 4px;
            }
            .header-logo { max-width: none; flex: 1 1 auto; }
            .header-logo-tagline {
                letter-spacing: 0.04em;
            }
            .header-logo-tagline .tagline-full { display: none; }
            .header-logo-tagline .tagline-short { display: inline; }
        }

        @media (max-width: 768px) {
            main { padding: 20px max(14px, env(safe-area-inset-right)) 24px max(14px, env(safe-area-inset-left)); }
            .header-logo-tagline {
                font-size: 8px;
                letter-spacing: 0.03em;
            }
        }

        @media (max-width: 420px) {
            .header-logo-text { font-size: 16px; }
            .header-logo-mark { width: 36px; height: 36px; }
            .header-logo { gap: 8px; }
            .header-logo-tagline {
                font-size: 7.5px;
                letter-spacing: 0.02em;
            }
        }

        /* ── COMPONENTES GLOBAIS DE UI ── */
        .page-header { margin-bottom: 32px; animation: fadeIn 0.5s ease-out; }
        .page-title { font-family: 'DM Serif Display', serif; font-size: clamp(1.35rem, 4vw + 0.5rem, 2rem); color: var(--primary); line-height: 1.15; word-break: break-word; overflow-wrap: anywhere; }
        .page-subtitle {
            font-size: clamp(13px, 2.5vw, 15px);
            color: var(--muted);
            margin-top: 6px;
            line-height: 1.5;
            max-width: 100%;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        /* Linha título + ações (dashboard, listas) */
        .card-toolbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        .card-toolbar .card-title { margin: 0; flex: 1 1 200px; min-width: 0; }
        @media (max-width: 560px) {
            .card-toolbar { flex-direction: column; align-items: stretch; }
            .card-toolbar .btn-analisar-wrap,
            .card-toolbar .btn-primary-custom { width: 100%; justify-content: center; }
        }

        .main-card {
            background: var(--surface);
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 10px 40px rgba(127,12,26,0.1);
            border: 1px solid var(--border);
            margin-bottom: 30px;
            animation: fadeIn 0.6s ease-out;
            max-width: 100%;
            min-width: 0;
        }

        @media (max-width: 640px) {
            .main-card { padding: 20px 16px; border-radius: 18px; }
        }

        @media (max-width: 420px) {
            .main-card { padding: 16px 12px; border-radius: 16px; }
        }

        .card-title { font-family: 'DM Serif Display', serif; font-size: clamp(1.05rem, 2.5vw + 0.6rem, 1.375rem); color: var(--primary); display: flex; align-items: center; flex-wrap: wrap; gap: 10px; min-width: 0; }

        /* Mídia / gráficos nunca estouram a viewport */
        img, video, iframe {
            max-width: 100%;
            height: auto;
        }
        .analise-shap-canvas {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .analise-shap-canvas canvas {
            max-width: 100%;
        }

        /* Empilhamento de páginas (gestante, cohort) */
        .page-stack {
            max-width: 1152px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: clamp(16px, 3vw, 28px);
            padding-bottom: clamp(20px, 4vw, 32px);
            min-width: 0;
        }

        /* ── Tabelas (layout moderno) ── */
        .table-container {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            overflow-x: auto;
            margin-top: 20px;
            border-radius: 20px;
            background: var(--surface);
            box-shadow:
                0 12px 40px rgba(127, 12, 26, 0.07),
                0 0 0 1px rgba(127, 12, 26, 0.06);
            -webkit-overflow-scrolling: touch;
            overscroll-behavior-x: contain;
            scrollbar-width: thin;
            scrollbar-color: rgba(127, 12, 26, 0.35) transparent;
        }

        .table-container::-webkit-scrollbar {
            height: 8px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: rgba(127, 12, 26, 0.28);
            border-radius: 999px;
        }

        .table-container--flush { margin-top: 0; }

        .table-scroll-hint {
            display: block;
            font-size: 12px;
            color: var(--muted);
            margin: 0 0 10px;
            text-align: center;
            line-height: 1.4;
        }

        @media (min-width: 1100px) {
            .table-scroll-hint { display: none; }
        }

        table.data-table {
            width: 100%;
            min-width: 0;
            border-collapse: separate;
            border-spacing: 0;
            background: transparent;
        }

        /* Listas com muitas colunas (gestantes, WhatsApp, cohort index) */
        table.data-table--wide {
            min-width: 56rem;
        }

        table.data-table--compact {
            min-width: 0;
        }

        table.data-table thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: linear-gradient(180deg, #fff9f9 0%, #f8eaea 100%);
            color: var(--primary);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 14px 16px;
            text-align: left;
            border-bottom: 2px solid rgba(127, 12, 26, 0.14);
            white-space: nowrap;
        }

        table.data-table tbody td {
            padding: 14px 16px;
            font-size: 14px;
            color: var(--text);
            border-bottom: 1px solid rgba(240, 213, 213, 0.55);
            vertical-align: middle;
            line-height: 1.45;
            background: var(--surface);
        }

        table.data-table tbody tr {
            transition: background-color 0.15s ease;
        }

        table.data-table tbody tr:nth-child(even) td {
            background: rgba(253, 240, 240, 0.4);
        }

        table.data-table tbody tr:hover td {
            background: rgba(127, 12, 26, 0.055);
        }

        table.data-table tbody tr:last-child td {
            border-bottom: none;
        }

        table.data-table .td-num {
            font-variant-numeric: tabular-nums;
            text-align: center;
        }

        table.data-table thead th.td-actions,
        table.data-table tbody td.td-actions {
            text-align: right;
            vertical-align: middle;
            min-width: 13.5rem;
            width: 13.5rem;
        }

        table.data-table .td-actions-inner {
            display: flex;
            flex-direction: row;
            flex-wrap: nowrap;
            justify-content: flex-end;
            align-items: center;
            gap: 6px;
            width: max-content;
            max-width: 100%;
            margin-left: auto;
        }

        @media (max-width: 960px) {
            table.data-table thead th.td-actions,
            table.data-table tbody td.td-actions {
                min-width: 7.25rem;
                width: 7.25rem;
            }

            table.data-table .td-actions-inner {
                flex-direction: column;
                align-items: stretch;
                width: 100%;
                margin-left: 0;
            }
        }

        table.data-table th.td-nowrap,
        table.data-table td.td-nowrap {
            white-space: nowrap;
        }

        table.data-table th.td-cell-nome,
        table.data-table td.td-cell-nome {
            min-width: 9.5rem;
            max-width: 15rem;
            white-space: normal;
            word-break: break-word;
            line-height: 1.35;
        }

        table.data-table th.td-risco,
        table.data-table td.td-risco {
            min-width: 6.75rem;
            white-space: nowrap;
        }

        table.data-table th.td-cpf,
        table.data-table td.td-cpf {
            min-width: 8.75rem;
            white-space: nowrap;
        }

        table.data-table th.td-tel,
        table.data-table td.td-tel {
            min-width: 8.5rem;
            white-space: nowrap;
        }

        table.data-table th.td-date,
        table.data-table td.td-date {
            min-width: 6.75rem;
            white-space: nowrap;
        }

        /* Em tablet/celular: prioriza nome, risco e ações (melhor para demo) */
        @media (max-width: 900px) {
            table.data-table--wide {
                min-width: 32rem;
            }
            table.data-table--wide .td-cpf,
            table.data-table--wide .td-tel,
            table.data-table--wide .td-date {
                display: none;
            }
        }

        @media (max-width: 560px) {
            table.data-table--wide {
                min-width: 0;
            }
            table.data-table--wide .td-num--narrow {
                display: none;
            }
            table.data-table thead th.td-actions,
            table.data-table tbody td.td-actions {
                min-width: 5.5rem;
                width: auto;
            }
        }

        table.data-table th.td-msg,
        table.data-table td.td-msg {
            min-width: 11rem;
            max-width: 18rem;
            white-space: normal;
            word-break: break-word;
            line-height: 1.4;
        }

        table.data-table th.td-num--narrow,
        table.data-table td.td-num--narrow {
            white-space: nowrap;
            min-width: 4.75rem;
        }

        table.data-table tbody tr.data-table-empty td {
            text-align: center;
            color: var(--muted);
            padding: 40px 24px;
            font-size: 15px;
            background: var(--surface) !important;
        }

        .btn-table {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 10px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-family: inherit;
            line-height: 1.2;
            white-space: nowrap;
            flex: 0 0 auto;
            box-sizing: border-box;
            -webkit-appearance: none;
            appearance: none;
            transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
        }

        @media (max-width: 960px) {
            table.data-table .btn-table {
                width: 100%;
                padding: 8px 10px;
            }
        }

        .btn-table:hover { transform: translateY(-1px); }

        .btn-table--primary {
            background: linear-gradient(135deg, var(--primary), var(--accent-mid));
            color: #fff;
            box-shadow: 0 2px 12px rgba(192, 57, 43, 0.28);
        }

        .btn-table--secondary {
            background: rgba(192, 57, 43, 0.11);
            color: var(--primary);
        }

        .btn-table--danger {
            background: rgba(192, 57, 43, 0.16);
            color: var(--primary);
        }

        .btn-table--icon {
            width: 36px;
            height: 36px;
            padding: 0;
            flex: 0 0 36px;
        }
        .btn-table--icon svg {
            display: block;
            width: 16px;
            height: 16px;
        }
        .btn-table--icon.is-disabled {
            opacity: 0.45;
            pointer-events: none;
            cursor: not-allowed;
        }
        .td-actions-inner--icons {
            gap: 4px;
        }
        .td-actions-inner--icons form {
            display: inline-flex;
            margin: 0;
            flex: 0 0 auto;
        }
        .td-actions-group {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .td-actions-sep {
            display: inline-block;
            width: 1px;
            height: 22px;
            margin: 0 4px;
            background: var(--border);
            flex: 0 0 1px;
        }
        @media (max-width: 960px) {
            table.data-table .td-actions-inner--icons {
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: flex-end;
                width: max-content;
                margin-left: auto;
            }
            table.data-table .td-actions-inner--icons .btn-table,
            table.data-table .btn-table--icon {
                width: 36px;
                height: 36px;
                flex: 0 0 36px;
            }
        }

        .table-pagination {
            margin-top: 24px;
            padding-top: 8px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior-x: contain;
        }

        /* Paginação custom (vendor/pagination/cardioprenatal) — evita sobreposição do template Tailwind (-ml-px) */
        .cp-pagination {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            width: 100%;
            min-width: 0;
        }

        @media (min-width: 640px) {
            .cp-pagination {
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: space-between;
                align-items: center;
            }
        }

        .cp-pagination__summary {
            margin: 0;
            font-size: 13px;
            color: var(--muted);
            text-align: center;
            line-height: 1.45;
        }

        .cp-pagination__summary strong {
            color: var(--text);
            font-weight: 600;
        }

        .cp-pagination__list {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 8px;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .cp-pagination__item a,
        .cp-pagination__item--active span,
        .cp-pagination__item--disabled span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.5rem;
            min-height: 2.5rem;
            padding: 0 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--primary);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            font-variant-numeric: tabular-nums;
            box-sizing: border-box;
        }

        .cp-pagination__item a:hover {
            background: rgba(253, 240, 240, 0.85);
            border-color: rgba(192, 57, 43, 0.35);
        }

        .cp-pagination__item--active span {
            background: linear-gradient(135deg, var(--primary), var(--accent-mid));
            color: #fff;
            border-color: transparent;
        }

        .cp-pagination__item--disabled span {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }

        .cp-pagination__item--ellipsis span {
            min-width: auto;
            border: none;
            background: transparent;
            color: var(--muted);
            font-weight: 500;
            padding: 0 4px;
        }

        /* ── Formulários longos (consultas etc.) ── */
        .form-page-grid {
            display: grid;
            gap: 22px;
            grid-template-columns: 1fr;
        }

        @media (min-width: 768px) {
            .form-page-grid--2 { grid-template-columns: repeat(2, 1fr); }
        }

        @media (min-width: 768px) and (max-width: 1099px) {
            .form-page-grid--3 { grid-template-columns: repeat(2, 1fr); }
        }

        @media (min-width: 1100px) {
            .form-page-grid--3 { grid-template-columns: repeat(3, 1fr); }
        }

        .form-section {
            background: rgba(253, 240, 240, 0.42);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 22px 24px;
        }

        @media (max-width: 640px) {
            .form-section { padding: 16px 14px; border-radius: 14px; }
        }

        .form-section--wide {
            grid-column: 1 / -1;
        }

        @media (min-width: 1100px) {
            .form-page-grid--3 .form-section--span-2 {
                grid-column: span 2;
            }
        }

        .form-section-title {
            font-family: 'DM Serif Display', serif;
            font-size: 18px;
            color: var(--primary);
            margin: 0 0 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }

        .form-field { margin-bottom: 16px; }
        .form-field:last-child { margin-bottom: 0; }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid var(--border);
            font-family: inherit;
            font-size: 15px;
            background: var(--surface);
            color: var(--text);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: rgba(192, 57, 43, 0.5);
            box-shadow: 0 0 0 3px rgba(192, 57, 43, 0.12);
        }

        .form-field-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: 1fr;
        }

        @media (min-width: 520px) {
            .form-field-grid--2 { grid-template-columns: repeat(2, 1fr); }
            .form-field-grid--3 { grid-template-columns: repeat(3, 1fr); }
        }

        .form-req { color: var(--risk-high); font-weight: 700; }

        .consulta-form {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .consulta-form-hint {
            margin: 0 0 4px;
            padding: 12px 14px;
            border-radius: 12px;
            background: var(--status-info-bg);
            border: 1px solid var(--status-info-border);
            color: var(--text);
            font-size: 14px;
            line-height: 1.45;
        }

        .bool-grid {
            display: grid;
            gap: 10px;
            grid-template-columns: 1fr;
        }
        @media (min-width: 640px) {
            .bool-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (min-width: 960px) {
            .bool-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }

        .bool-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 12px;
            background: var(--surface);
            border: 1px solid var(--border);
            min-height: 48px;
        }
        .bool-row-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            line-height: 1.3;
            flex: 1 1 auto;
            min-width: 0;
        }
        .bool-pills {
            display: inline-flex;
            gap: 4px;
            flex-shrink: 0;
            padding: 3px;
            border-radius: 999px;
            background: rgba(240, 213, 213, 0.45);
        }
        .bool-pill {
            position: relative;
            cursor: pointer;
            margin: 0;
        }
        .bool-pill input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .bool-pill span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 44px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
            transition: background .15s ease, color .15s ease;
        }
        .bool-pill input:checked + span {
            background: var(--primary);
            color: #fff;
        }
        .bool-pill input:focus-visible + span {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        .form-alert-error {
            background: var(--accent-light);
            border: 1px solid var(--border);
            color: var(--primary);
            padding: 16px 18px;
            border-radius: 16px;
            margin-bottom: 24px;
        }

        .form-alert-error ul {
            margin: 10px 0 0;
            padding-left: 1.15rem;
            line-height: 1.55;
            font-size: 14px;
        }

        .form-actions {
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: space-between;
            align-items: center;
        }

        @media (max-width: 480px) {
            .form-actions { flex-direction: column-reverse; align-items: stretch; }
            .form-actions > * { width: 100%; justify-content: center; }
            .form-actions-end { width: 100%; justify-content: stretch; }
            .form-actions-end .btn-primary-custom,
            .form-actions-end .btn-secondary-outline { flex: 1; justify-content: center; }
        }

        .form-actions-end {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: flex-end;
            align-items: center;
        }

        .btn-secondary-outline {
            display: inline-flex;
            align-items: center;
            padding: 12px 22px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            font-family: inherit;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .btn-secondary-outline:hover {
            background: rgba(253, 240, 240, 0.6);
        }

        .btn-secondary-outline:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        /* Leitura de consulta (ficha da gestante) */
        .gestante-form-actions {
            align-items: center;
        }

        @media (max-width: 480px) {
            .gestante-form-actions {
                flex-direction: column;
                align-items: stretch;
            }
            .gestante-form-actions .btn-primary-custom,
            .gestante-form-actions a {
                width: 100%;
                justify-content: center;
            }
        }

        .gestante-header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
        }

        @media (max-width: 520px) {
            .gestante-header-actions {
                width: 100%;
                justify-content: stretch;
            }
            .gestante-header-actions > a,
            .gestante-header-actions > button {
                flex: 1 1 calc(50% - 5px);
                justify-content: center;
                min-width: 0;
            }
        }

        @media (max-width: 380px) {
            .gestante-header-actions { flex-direction: column; }
            .gestante-header-actions > a,
            .gestante-header-actions > button { flex: none; width: 100%; }
        }

        .consulta-resumo-head {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 4px;
        }

        @media (max-width: 560px) {
            .consulta-resumo-head {
                flex-direction: column;
                align-items: stretch;
            }
            .consulta-resumo-head .btn-table { align-self: flex-start; }
        }

        .consulta-pill {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.02em;
            border: 1px solid transparent;
        }

        /* Risco clínico (CatBoost) */
        .consulta-pill--risk-low {
            background: var(--risk-low-bg);
            color: var(--risk-low);
            border-color: var(--risk-low-border);
        }
        .consulta-pill--risk-mid {
            background: var(--risk-mid-bg);
            color: var(--risk-mid);
            border-color: var(--risk-mid-border);
        }
        .consulta-pill--risk-high {
            background: var(--risk-high-bg);
            color: var(--risk-high);
            border-color: var(--risk-high-border);
        }

        /* Status genérico (ok / atenção / erro de sistema) */
        .consulta-pill--ok {
            background: var(--risk-low-bg);
            color: var(--risk-low);
            border-color: var(--risk-low-border);
        }
        .consulta-pill--warn {
            background: var(--risk-mid-bg);
            color: var(--risk-mid);
            border-color: var(--risk-mid-border);
        }
        .consulta-pill--alert {
            background: var(--risk-high-bg);
            color: var(--risk-high);
            border-color: var(--risk-high-border);
        }
        .consulta-pill--info {
            background: var(--status-info-bg);
            color: var(--status-info);
            border-color: var(--status-info-border);
        }

        .clinical-msg-gestante {
            padding: 18px;
            margin-bottom: 16px;
            border-radius: 14px;
            background: var(--status-info-bg);
            border: 1px solid var(--status-info-border);
            border-left: 4px solid var(--status-info);
        }
        .clinical-msg-evolucao {
            padding: 18px;
            margin-bottom: 16px;
            border-radius: 14px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-left: 4px solid var(--primary);
        }

        .consulta-valor {
            margin: 4px 0 0;
            font-size: 15px;
            font-weight: 600;
            color: var(--text);
            line-height: 1.45;
        }

        .consulta-acoes-status {
            margin: 0 0 18px;
            font-size: 14px;
            color: var(--muted);
        }
        .consulta-acoes-status strong {
            color: var(--text);
            font-weight: 600;
        }
        .consulta-check-list {
            display: grid;
            gap: 10px;
            margin: 0 0 8px;
        }
        .consulta-check-form { margin: 0; }
        .consulta-check {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin: 0;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--surface);
            cursor: pointer;
            position: relative;
            transition: border-color .2s ease, background .2s ease, box-shadow .2s ease;
        }
        .consulta-check:hover {
            border-color: rgba(127, 12, 26, 0.28);
        }
        .consulta-check.is-done {
            background: rgba(127, 12, 26, 0.04);
            border-color: rgba(127, 12, 26, 0.22);
            cursor: default;
        }
        .consulta-check.is-disabled {
            opacity: 0.62;
            cursor: not-allowed;
        }
        .consulta-check input {
            position: absolute;
            opacity: 0;
            width: 1px;
            height: 1px;
            margin: 0;
            pointer-events: none;
            appearance: none;
        }
        .consulta-check-box {
            flex-shrink: 0;
            width: 18px;
            height: 18px;
            margin-top: 2px;
            border: 1.5px solid var(--primary);
            border-radius: 5px;
            background: #fff;
            box-sizing: border-box;
            transition: background .2s ease, border-color .2s ease, transform .15s ease;
            position: relative;
        }
        .consulta-check-box::after {
            content: '';
            position: absolute;
            left: 5px;
            top: 1px;
            width: 5px;
            height: 9px;
            border: solid #fff;
            border-width: 0 2px 2px 0;
            transform: scale(0) rotate(45deg);
            transition: transform .16s ease;
        }
        .consulta-check input:checked + .consulta-check-box,
        .consulta-check.is-done .consulta-check-box {
            background: var(--primary);
            border-color: var(--primary);
        }
        .consulta-check input:checked + .consulta-check-box::after,
        .consulta-check.is-done .consulta-check-box::after {
            transform: scale(1) rotate(45deg);
        }
        .consulta-check input:focus-visible + .consulta-check-box {
            box-shadow: 0 0 0 3px rgba(127, 12, 26, 0.16);
        }
        .consulta-check-copy {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        .consulta-check-copy strong {
            font-size: 14.5px;
            font-weight: 600;
            color: var(--text);
            line-height: 1.35;
        }
        .consulta-check-copy small {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.45;
        }
        .consulta-check.is-done .consulta-check-copy small {
            color: var(--primary);
            font-weight: 500;
        }
        .consulta-outras-acoes {
            margin-top: 22px;
            padding-top: 18px;
            border-top: 1px solid var(--border);
        }
        .consulta-outras-acoes h3 {
            margin: 0 0 10px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .consulta-outras-acoes-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 4px;
        }
        .consulta-acao-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 10px;
            border-radius: 8px;
            border: 1px solid transparent;
            background: transparent;
            color: var(--text);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            font-family: inherit;
            transition: color .15s ease, background .15s ease, border-color .15s ease;
        }
        .consulta-acao-link svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
            color: var(--muted);
        }
        .consulta-acao-link:hover {
            color: var(--primary);
            background: rgba(127, 12, 26, 0.05);
            border-color: rgba(127, 12, 26, 0.12);
        }
        .consulta-acao-link:hover svg { color: var(--primary); }
        @media (max-width: 640px) {
            .consulta-outras-acoes-list { flex-direction: column; }
            .consulta-acao-link { width: 100%; }
        }

        .consulta-chd-box {
            margin-top: 12px;
            padding: 14px 16px;
            border-radius: 14px;
            background: rgba(253, 240, 240, 0.65);
            border: 1px solid var(--border);
            font-size: 14px;
            font-weight: 600;
            color: var(--primary);
        }

        @media (max-width: 768px) {
            table.data-table thead th,
            table.data-table tbody td {
                padding: 12px 14px;
                font-size: 13px;
            }

            table.data-table thead th {
                font-size: 10px;
            }
        }

        @media (max-width: 480px) {
            table.data-table thead th,
            table.data-table tbody td {
                padding: 10px 11px;
                font-size: 12px;
            }

            table.data-table thead th {
                font-size: 9px;
                letter-spacing: 0.05em;
            }

            .btn-table {
                padding: 8px 10px;
                font-size: 12px;
            }
        }

        main .main-card img,
        .grafico-item img {
            max-width: 100%;
            height: auto;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--accent-mid));
            color: white;
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            line-height: 1.2;
            white-space: nowrap;
            box-sizing: border-box;
            -webkit-appearance: none;
            appearance: none;
            transition: all 0.3s;
            text-decoration: none;
            max-width: 100%;
        }
        .btn-primary-custom:hover { opacity: 0.9; transform: scale(1.02); }

        @media (max-width: 480px) {
            .btn-primary-custom {
                white-space: normal;
                text-align: center;
                line-height: 1.3;
                height: auto;
                min-height: 44px;
                padding: 12px 16px;
            }
        }

        /* KPIs compartilhados (dashboard + cohort) */
        .dash-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 132px), 1fr));
            gap: 10px;
            margin-bottom: 18px;
        }
        .dash-kpi {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 16px;
        }
        .dash-kpi-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .dash-kpi-value {
            font-family: 'DM Serif Display', serif;
            font-size: clamp(1.35rem, 3.5vw, 1.75rem);
            color: var(--text);
            margin-top: 4px;
            line-height: 1.1;
            word-break: break-word;
        }
        .dash-kpi-value a { color: inherit; text-decoration: none; }
        .dash-kpi-value a:hover { color: var(--accent); }
        .dash-kpi-value--accent { color: var(--accent); }
        .dash-kpi-value--warn { color: var(--risk-mid); }
        .dash-kpi-value--ok { color: var(--risk-low); }
        .dash-kpi-value--risk-high { color: var(--risk-high); }
        .dash-empty {
            margin: 0;
            padding: 12px 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
            text-align: center;
        }
        .dash-empty a { color: var(--accent); font-weight: 600; }

        /* Modal genérico (gestantes etc.)
           Importante: não usar display:flex no estado fechado — conflita com .hidden do Tailwind. */
        .app-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 200;
            display: none;
            align-items: center;
            justify-content: center;
            padding: max(12px, env(safe-area-inset-top)) max(12px, env(safe-area-inset-right)) max(12px, env(safe-area-inset-bottom)) max(12px, env(safe-area-inset-left));
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        .app-modal-overlay.is-open {
            display: flex;
        }
        .app-modal-panel {
            max-width: 400px;
            width: 100%;
            margin: auto;
        }

        .app-modal-panel--lg {
            max-width: min(560px, calc(100vw - 32px));
        }

        .app-modal-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 12px;
        }
        @media (max-width: 420px) {
            .app-modal-actions { flex-direction: column; align-items: stretch; }
            .app-modal-actions button,
            .app-modal-actions .btn-primary-custom { width: 100%; justify-content: center; }
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>

<body>

    <!-- Header -->
    <header>
        <div class="header-inner">
            <!-- Logo -->
            <a href="{{ auth()->user()?->homeRoute() ?? url('/') }}" class="header-logo" title="Cardioprenatal. Rastreio de cardiopatias congênitas em gestantes diabéticas.">
                <span class="header-logo-mark">
                    @include('partials.brand-logo', ['variant' => 'header', 'decorative' => true])
                </span>
                <span class="header-logo-text-wrap">
                    <span class="header-logo-text">Cardioprenatal</span>
                    <span class="header-logo-tagline">
                        <span class="tagline-full">Cardiopatias congênitas em gestantes diabéticas</span>
                        <span class="tagline-short">Gestantes diabéticas</span>
                    </span>
                </span>
            </a>

            <!-- Nav desktop -->
            <nav class="nav-desktop">
                @include('partials.app-nav-links')
                <div class="nav-sep"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-logout">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                        Sair
                    </button>
                </form>
            </nav>

            <!-- Botão mobile -->
            <button type="button" class="btn-mobile-menu" id="menuButton" aria-label="Abrir menu" aria-expanded="false" aria-controls="mobileMenu">
                <svg class="icon-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
                <svg class="icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <!-- Nav mobile -->
        <nav class="nav-mobile" id="mobileMenu" role="navigation" aria-label="Menu principal">
            @include('partials.app-nav-links', ['mobile' => true])
            <div class="nav-mobile-sep"></div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-logout" style="width:100%; justify-content:center;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    Sair
                </button>
            </form>
        </nav>
    </header>

    <div class="nav-mobile-overlay" id="mobileMenuOverlay" hidden></div>

    <!-- Conteúdo -->
    <main>
        @yield('content')
    </main>

    <footer class="app-site-footer">
        <span class="footer-brand-icons" aria-hidden="true">
            @include('partials.brand-logo', ['variant' => 'footer', 'decorative' => true])
        </span>
        <span>Cardioprenatal. Rastreio de cardiopatias congênitas em gestantes diabéticas.</span>
    </footer>

    <script>
        (function () {
            const menuButton = document.getElementById('menuButton');
            const mobileMenu = document.getElementById('mobileMenu');
            const overlay = document.getElementById('mobileMenuOverlay');
            if (!menuButton || !mobileMenu) return;

            function setMenuOpen(open) {
                mobileMenu.classList.toggle('open', open);
                menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
                menuButton.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
                document.body.classList.toggle('nav-mobile-open', open);
                if (overlay) {
                    overlay.classList.toggle('is-open', open);
                    overlay.hidden = !open;
                }
            }

            function closeMenu() {
                setMenuOpen(false);
            }

            menuButton.addEventListener('click', () => {
                const open = menuButton.getAttribute('aria-expanded') !== 'true';
                setMenuOpen(open);
            });

            if (overlay) {
                overlay.addEventListener('click', closeMenu);
            }

            mobileMenu.querySelectorAll('a.nav-link, .btn-logout').forEach((el) => {
                el.addEventListener('click', closeMenu);
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && menuButton.getAttribute('aria-expanded') === 'true') {
                    closeMenu();
                    menuButton.focus();
                }
            });

            window.addEventListener('resize', () => {
                if (window.matchMedia('(min-width: 1200px)').matches) {
                    closeMenu();
                }
            });

            // Marca o link ativo (path exato ou prefixo da seção)
            const currentPath = window.location.pathname.replace(/\/$/, '') || '/';
            document.querySelectorAll('.nav-link').forEach(link => {
                const href = (link.getAttribute('href') || '').replace(/\/$/, '') || '/';
                if (href === currentPath || (href !== '/' && currentPath.startsWith(href + '/'))) {
                    link.classList.add('active');
                }
            });
        })();
    </script>

    @stack('scripts')

</body>

</html>