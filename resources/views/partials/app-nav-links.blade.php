@php
    $svgAttrs = ! empty($mobile) ? 'width="16" height="16"' : '';
@endphp

@can(\App\Models\User::ABILITY_DASHBOARD)
    <a href="{{ route('dashboard') }}" class="nav-link{{ request()->routeIs('dashboard') ? ' active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" {!! $svgAttrs !!}>
            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
            <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
        </svg>
        Dashboard
    </a>
@endcan
@can(\App\Models\User::ABILITY_GESTANTES_VIEW)
    <a href="{{ route('gestantes.index') }}" class="nav-link{{ request()->routeIs('gestantes.*') ? ' active' : '' }}">
        <svg class="nav-icon-baby" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" {!! $svgAttrs !!} aria-hidden="true">
            <circle cx="12" cy="7.5" r="3.2"/>
            <circle cx="9.5" cy="12" r="0.65" fill="currentColor" stroke="none"/>
            <circle cx="14.5" cy="12" r="0.65" fill="currentColor" stroke="none"/>
            <path d="M10 15.5c.6.9 1.4 1.3 2 1.3s1.4-.4 2-1.3"/>
            <path d="M6 20c0-2.8 2.6-5 6-5s6 2.2 6 5"/>
        </svg>
        Gestantes
    </a>
@endcan
@can(\App\Models\User::ABILITY_ACOMPANHAMENTOS)
    <a href="{{ route('acompanhamentos.index') }}" class="nav-link{{ request()->routeIs('acompanhamentos.*') ? ' active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" {!! $svgAttrs !!} aria-hidden="true">
            <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
        </svg>
        @if (! empty($mobile))
            Fila eco
        @else
            <span class="nav-label-full">Fila ecocardiograma</span>
            <span class="nav-label-short">Fila eco</span>
        @endif
    </a>
@endcan
@can(\App\Models\User::ABILITY_WHATSAPP)
    <a href="{{ route('whatsapp.index') }}" class="nav-link{{ request()->routeIs('whatsapp.*') ? ' active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" {!! $svgAttrs !!}>
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
        </svg>
        WhatsApp
    </a>
    <a href="{{ route('historico-whatsapp.index') }}" class="nav-link{{ request()->routeIs('historico-whatsapp.*') ? ' active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" {!! $svgAttrs !!}>
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        @if (! empty($mobile))
            Histórico WhatsApp
        @else
            <span class="nav-label-full">Histórico WhatsApp</span>
            <span class="nav-label-short">Histórico</span>
        @endif
    </a>
@endcan
@can(\App\Models\User::ABILITY_CONSULTAS_IMPORT)
    <a href="{{ route('consultas.import') }}" class="nav-link{{ request()->routeIs('consultas.import') ? ' active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" {!! $svgAttrs !!}>
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
        </svg>
        @if (! empty($mobile))
            Importar consultas
        @else
            <span class="nav-label-full">Importar consultas</span>
            <span class="nav-label-short">Importar</span>
        @endif
    </a>
@endcan
