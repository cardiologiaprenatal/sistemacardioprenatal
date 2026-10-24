@extends('layouts.app')

@section('title', 'Acesso negado. Cardioprenatal')

@section('content')
    <div class="main-card" style="max-width: 560px; margin: 40px auto; text-align: center; padding: 40px 28px;">
        <p style="font-size: 12px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted); margin-bottom: 8px;">403</p>
        <h1 class="page-title" style="margin-bottom: 12px;">Acesso não permitido</h1>
        <p style="color: var(--muted); line-height: 1.55; margin-bottom: 24px;">
            Seu perfil ({{ auth()->user()?->roleLabel() }}) não tem permissão para esta tela.
        </p>
        <a href="{{ auth()->user()?->homeRoute() ?? url('/') }}" class="btn-primary-custom">Ir para a página inicial</a>
    </div>
@endsection
