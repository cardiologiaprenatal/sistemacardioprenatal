@php
    $variant = $variant ?? 'auth';
    $decorative = (bool) ($decorative ?? false);
@endphp
<img
    src="{{ asset('images/logo-cardioprenatal.jpg') }}"
    alt="{{ $decorative ? '' : 'CardioPrenatal' }}"
    @if ($decorative) aria-hidden="true" @endif
    class="brand-logo brand-logo--{{ $variant }}"
    width="512"
    height="512"
    decoding="async"
>

@once
<style>
    .brand-logo {
        display: block;
        width: auto;
        height: auto;
        max-width: 100%;
        object-fit: contain;
        object-position: center;
        flex-shrink: 0;
        background: #fff;
    }
    .brand-logo--header,
    .brand-logo--footer {
        width: 40px;
        height: 40px;
        object-fit: cover;
        object-position: center 28%;
    }
    .brand-logo--footer {
        width: 28px;
        height: 28px;
    }
    .brand-logo--auth {
        width: min(220px, 72vw);
        max-height: 220px;
        margin: 0 auto 4px;
        border-radius: 16px;
    }
    .brand-logo--auth-sm {
        width: min(148px, 58vw);
        max-height: 148px;
        margin: 0 auto 8px;
        border-radius: 12px;
    }
    .brand-logo--dash {
        width: min(96px, 42vw);
        max-height: 96px;
        border-radius: 10px;
    }
    @media (max-width: 640px) {
        .brand-logo--header {
            width: 34px;
            height: 34px;
        }
        .brand-logo--auth {
            width: min(156px, 54vw);
            max-height: 156px;
        }
        .brand-logo--auth-sm {
            width: min(120px, 48vw);
            max-height: 120px;
        }
        .brand-logo--dash {
            width: min(80px, 38vw);
            max-height: 80px;
        }
        .brand-logo--footer {
            width: 24px;
            height: 24px;
        }
    }
</style>
@endonce
