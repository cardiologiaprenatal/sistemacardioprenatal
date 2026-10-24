@php
    $done = (bool) ($done ?? false);
    $enabled = (bool) ($enabled ?? false);
    $action = $action ?? '';
    $title = $title ?? '';
    $hint = $hint ?? '';
    $hintDone = $hintDone ?? $hint;
@endphp

@if ($done)
    <label class="consulta-check is-done">
        <input type="checkbox" checked disabled>
        <span class="consulta-check-box" aria-hidden="true"></span>
        <span class="consulta-check-copy">
            <strong>{{ $title }}</strong>
            <small>{{ $hintDone }}</small>
        </span>
    </label>
@else
    <form method="POST" action="{{ $action }}" class="consulta-check-form">
        @csrf
        <label class="consulta-check{{ $enabled ? '' : ' is-disabled' }}">
            <input type="checkbox"
                   @disabled(! $enabled)
                   onchange="if (this.disabled || !this.checked) { this.checked = false; return; } this.form.submit();">
            <span class="consulta-check-box" aria-hidden="true"></span>
            <span class="consulta-check-copy">
                <strong>{{ $title }}</strong>
                <small>{{ $hint }}</small>
            </span>
        </label>
    </form>
@endif
