@php
    /** @var string $name */
    /** @var string $label */
    /** @var \App\Models\Consulta|null $consulta */
    $c = $consulta ?? null;
    $valor = (int) old($name, $c !== null ? (int) $c->{$name} : 0);
@endphp
<div class="bool-row">
    <span class="bool-row-label" id="label-{{ $name }}">{{ $label }}</span>
    <div class="bool-pills" role="radiogroup" aria-labelledby="label-{{ $name }}">
        <label class="bool-pill">
            <input type="radio" name="{{ $name }}" value="0" @checked($valor === 0)>
            <span>Não</span>
        </label>
        <label class="bool-pill">
            <input type="radio" name="{{ $name }}" value="1" @checked($valor === 1)>
            <span>Sim</span>
        </label>
    </div>
</div>
