@php
    /** @var \App\Models\Consulta|null $consulta */
    $c = $consulta ?? null;
@endphp

<div class="consulta-form">
    <p class="consulta-form-hint">
        Preencha o essencial no topo. Nos fatores clínicos, o padrão é <strong>Não</strong>. Marque <strong>Sim</strong> só quando houver.
    </p>

    <section class="form-section form-section--wide">
        <h3 class="form-section-title">1. Consulta</h3>
        <div class="form-field-grid form-field-grid--3">
            <div class="form-field">
                <label class="form-label" for="data_consulta">Data <span class="form-req">*</span></label>
                <input class="form-input" type="date" name="data_consulta" id="data_consulta" required
                       value="{{ old('data_consulta', $c && $c->data_consulta ? $c->data_consulta->format('Y-m-d') : date('Y-m-d')) }}">
            </div>
            <div class="form-field">
                <label class="form-label" for="idade_gestacional">Idade gestacional (sem.) <span class="form-req">*</span></label>
                <input class="form-input" type="number" name="idade_gestacional" id="idade_gestacional" min="4" max="42" required
                       value="{{ old('idade_gestacional', $c?->idade_gestacional) }}">
            </div>
            <div class="form-field">
                <label class="form-label" for="idade_materna">Idade materna</label>
                <input class="form-input" type="number" name="idade_materna" id="idade_materna" min="10" max="60"
                       value="{{ old('idade_materna', $c?->idade_materna) }}">
            </div>
            <div class="form-field">
                <label class="form-label" for="etnia">Etnia</label>
                <input class="form-input" type="text" name="etnia" id="etnia"
                       value="{{ old('etnia', $c?->etnia) }}" placeholder="Ex.: Branca, Parda…">
            </div>
            <div class="form-field">
                <label class="form-label" for="imc_pre_gestacional">IMC pré-gestacional</label>
                <input class="form-input" type="number" step="0.1" name="imc_pre_gestacional" id="imc_pre_gestacional"
                       value="{{ old('imc_pre_gestacional', $c?->imc_pre_gestacional) }}">
            </div>
            <div class="form-field">
                <label class="form-label" for="paridade">Paridade</label>
                <input class="form-input" type="number" name="paridade" id="paridade" min="0" max="20"
                       value="{{ old('paridade', $c?->paridade) }}">
            </div>
            <div class="form-field">
                <label class="form-label" for="abortamentos_previos">Abortamentos prévios</label>
                <input class="form-input" type="number" name="abortamentos_previos" id="abortamentos_previos" min="0" max="20"
                       value="{{ old('abortamentos_previos', $c?->abortamentos_previos) }}">
            </div>
            <div class="form-field">
                <label class="form-label" for="tipo_gestacao">Tipo de gestação</label>
                <select class="form-select" name="tipo_gestacao" id="tipo_gestacao">
                    <option value="">Selecione…</option>
                    <option value="única" @selected(old('tipo_gestacao', $c?->tipo_gestacao) === 'única')>Única</option>
                    <option value="gemelar" @selected(old('tipo_gestacao', $c?->tipo_gestacao) === 'gemelar')>Gemelar</option>
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="corionicidade">Corionicidade</label>
                <input class="form-input" type="text" name="corionicidade" id="corionicidade"
                       value="{{ old('corionicidade', $c?->corionicidade) }}" placeholder="Ex.: monocoriônica">
            </div>
        </div>
        <div class="bool-grid" style="margin-top: 8px;">
            @include('consultas.partials.bool-pill', ['name' => 'usg_precoce_confirmada', 'label' => 'USG precoce confirmada', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'obesidade_pre_gestacional', 'label' => 'Obesidade pré-gestacional', 'consulta' => $c])
        </div>
    </section>

    <section class="form-section form-section--wide">
        <h3 class="form-section-title">2. Antecedentes</h3>
        <div class="bool-grid">
            @include('consultas.partials.bool-pill', ['name' => 'historico_natimorto', 'label' => 'Histórico de natimorto', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'historico_filho_anterior_chd', 'label' => 'Filho anterior com cardiopatia congênita', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'historico_familiar_chd', 'label' => 'Histórico familiar de cardiopatia congênita', 'consulta' => $c])
        </div>
    </section>

    <section class="form-section form-section--wide">
        <h3 class="form-section-title">3. Comorbidades e infecções</h3>
        <div class="bool-grid">
            @include('consultas.partials.bool-pill', ['name' => 'diabetes_pre_gestacional', 'label' => 'Diabetes pré-gestacional', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'diabetes_gestacional', 'label' => 'Diabetes gestacional', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'hipertensao_cronica', 'label' => 'Hipertensão crônica', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'hipertensao', 'label' => 'Hipertensão (atual)', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'hipertensao_pre_eclampsia', 'label' => 'Pré-eclâmpsia', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'lupus_eritomatoso_sistemico', 'label' => 'Lúpus', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'fenilcetonuria', 'label' => 'Fenilcetonúria', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'doencas_tireoidianas', 'label' => 'Doenças tireoidianas', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'rubeola', 'label' => 'Rubéola', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'citomegalovirus', 'label' => 'Citomegalovírus', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'toxoplasmose', 'label' => 'Toxoplasmose', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'sifilis', 'label' => 'Sífilis', 'consulta' => $c])
        </div>
    </section>

    <section class="form-section form-section--wide">
        <h3 class="form-section-title">4. Medicações e exposições</h3>
        <div class="bool-grid">
            @include('consultas.partials.bool-pill', ['name' => 'uso_isotretinoina', 'label' => 'Isotretinoína', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'uso_acido_valproico', 'label' => 'Ácido valproico', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'uso_litio', 'label' => 'Lítio', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'uso_medicamentos', 'label' => 'Outros medicamentos', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'tabagismo', 'label' => 'Tabagismo', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'alcoolismo', 'label' => 'Álcool', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'drogas_ilicitas', 'label' => 'Drogas ilícitas', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'exposicao_ocupacional', 'label' => 'Exposição ocupacional', 'consulta' => $c])
        </div>
    </section>

    <section class="form-section form-section--wide">
        <h3 class="form-section-title">5. Sinais vitais e laboratório</h3>
        <div class="form-field-grid form-field-grid--3">
            <div class="form-field">
                <label class="form-label" for="pressao_sistolica">Pressão sistólica</label>
                <input class="form-input" type="number" name="pressao_sistolica" id="pressao_sistolica"
                       value="{{ old('pressao_sistolica', $c?->pressao_sistolica) }}">
            </div>
            <div class="form-field">
                <label class="form-label" for="bpm_materno">BPM materno</label>
                <input class="form-input" type="number" name="bpm_materno" id="bpm_materno"
                       value="{{ old('bpm_materno', $c?->bpm_materno) }}">
            </div>
            <div class="form-field">
                <label class="form-label" for="saturacao">Saturação (%)</label>
                <input class="form-input" type="number" name="saturacao" id="saturacao" min="0" max="100"
                       value="{{ old('saturacao', $c?->saturacao) }}">
            </div>
            <div class="form-field">
                <label class="form-label" for="temperatura_corporal">Temperatura (°C)</label>
                <input class="form-input" type="number" step="0.1" name="temperatura_corporal" id="temperatura_corporal"
                       value="{{ old('temperatura_corporal', $c?->temperatura_corporal) }}">
            </div>
            <div class="form-field">
                <label class="form-label" for="altura">Altura (cm)</label>
                <input class="form-input" type="number" step="0.1" name="altura" id="altura"
                       value="{{ old('altura', $c?->altura) }}">
            </div>
            <div class="form-field">
                <label class="form-label" for="peso">Peso (kg)</label>
                <input class="form-input" type="number" step="0.1" name="peso" id="peso"
                       value="{{ old('peso', $c?->peso) }}">
            </div>
            <div class="form-field">
                <label class="form-label" for="glicemia_jejum">Glicemia jejum</label>
                <input class="form-input" type="number" step="0.01" name="glicemia_jejum" id="glicemia_jejum"
                       value="{{ old('glicemia_jejum', $c?->glicemia_jejum) }}">
            </div>
            <div class="form-field">
                <label class="form-label" for="glicemia_pos_prandial">Glicemia pós-prandial</label>
                <input class="form-input" type="number" step="0.01" name="glicemia_pos_prandial" id="glicemia_pos_prandial"
                       value="{{ old('glicemia_pos_prandial', $c?->glicemia_pos_prandial) }}">
            </div>
            <div class="form-field">
                <label class="form-label" for="hba1c">HbA1c (%)</label>
                <input class="form-input" type="number" step="0.01" name="hba1c" id="hba1c" min="0" max="20"
                       value="{{ old('hba1c', $c?->hba1c) }}">
            </div>
        </div>
    </section>

    <section class="form-section form-section--wide">
        <h3 class="form-section-title">6. Dados fetais</h3>
        <div class="form-field-grid form-field-grid--3">
            <div class="form-field">
                <label class="form-label" for="frequencia_cardiaca_fetal">FC fetal</label>
                <input class="form-input" type="number" name="frequencia_cardiaca_fetal" id="frequencia_cardiaca_fetal"
                       value="{{ old('frequencia_cardiaca_fetal', $c?->frequencia_cardiaca_fetal) }}">
            </div>
            <div class="form-field">
                <label class="form-label" for="circunferencia_cefalica_fetal_mm">Circ. cefálica (mm)</label>
                <input class="form-input" type="number" step="0.1" name="circunferencia_cefalica_fetal_mm" id="circunferencia_cefalica_fetal_mm"
                       value="{{ old('circunferencia_cefalica_fetal_mm', $c?->circunferencia_cefalica_fetal_mm) }}">
            </div>
            <div class="form-field">
                <label class="form-label" for="circunferencia_abdominal_mm">Circ. abdominal (mm)</label>
                <input class="form-input" type="number" step="0.1" name="circunferencia_abdominal_mm" id="circunferencia_abdominal_mm"
                       value="{{ old('circunferencia_abdominal_mm', $c?->circunferencia_abdominal_mm) }}">
            </div>
            <div class="form-field">
                <label class="form-label" for="comprimento_femur_mm">Fêmur (mm)</label>
                <input class="form-input" type="number" step="0.1" name="comprimento_femur_mm" id="comprimento_femur_mm"
                       value="{{ old('comprimento_femur_mm', $c?->comprimento_femur_mm) }}">
            </div>
            <div class="form-field">
                <label class="form-label" for="translucencia_nucal_mm">TN (mm)</label>
                <input class="form-input" type="number" step="0.1" name="translucencia_nucal_mm" id="translucencia_nucal_mm"
                       value="{{ old('translucencia_nucal_mm', $c?->translucencia_nucal_mm) }}">
            </div>
        </div>
        <div class="bool-grid" style="margin-top: 12px;">
            @include('consultas.partials.bool-pill', ['name' => 'translucencia_nucal_aumentada', 'label' => 'TN aumentada', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'regurgitacao_tricuspide_fetal', 'label' => 'Regurgitação tricúspide', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'malformacoes_extracardiacas_associadas', 'label' => 'Malformações extracardíacas', 'consulta' => $c])
            @include('consultas.partials.bool-pill', ['name' => 'crescimento_fetal_rcf_iugr', 'label' => 'RCF / IUGR', 'consulta' => $c])
        </div>
    </section>

    <section class="form-section form-section--wide">
        <h3 class="form-section-title">7. Avaliação cardíaca</h3>
        <div class="form-field-grid form-field-grid--3">
            <div class="form-field">
                <label class="form-label" for="doppler_ducto_venoso">Doppler ducto venoso</label>
                <select class="form-select" name="doppler_ducto_venoso" id="doppler_ducto_venoso">
                    <option value="">Selecione…</option>
                    @foreach (['Ausente', 'Fluxo normal', 'Fluxo aumentado', 'Fluxo reverso'] as $opt)
                        <option value="{{ $opt }}" @selected(old('doppler_ducto_venoso', $c?->doppler_ducto_venoso) === $opt)>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="eixo_cardiaco">Eixo cardíaco</label>
                <input class="form-input" type="text" name="eixo_cardiaco" id="eixo_cardiaco"
                       value="{{ old('eixo_cardiaco', $c?->eixo_cardiaco) }}" placeholder="Ex.: 45°">
            </div>
            <div class="form-field">
                <label class="form-label" for="quatro_camaras">Quatro câmaras</label>
                <select class="form-select" name="quatro_camaras" id="quatro_camaras">
                    <option value="">Selecione…</option>
                    @foreach (['Normal', 'Não visível'] as $opt)
                        <option value="{{ $opt }}" @selected(old('quatro_camaras', $c?->quatro_camaras) === $opt)>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="tipo_chd">Tipo de cardiopatia congênita</label>
                <select class="form-select" name="tipo_chd" id="tipo_chd">
                    <option value="">Selecione…</option>
                    @php
                        $tipos = [
                            'DSV — Defeito do Septo Ventricular' => 'DSV: defeito do septo ventricular',
                            'DSA — Defeito do Septo Atrial' => 'DSA: defeito do septo atrial',
                            'Tetralogia de Fallot' => 'Tetralogia de Fallot',
                            'TGA — Transposição das Grandes Artérias' => 'TGA: transposição das grandes artérias',
                            'Hipoplasia do Coração Esquerdo' => 'Hipoplasia VE',
                        ];
                    @endphp
                    @foreach ($tipos as $val => $label)
                        <option value="{{ $val }}" @selected(old('tipo_chd', $c?->tipo_chd) === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="bool-grid" style="margin-top: 12px;">
            @include('consultas.partials.bool-pill', ['name' => 'chd_confirmada', 'label' => 'Cardiopatia congênita confirmada', 'consulta' => $c])
        </div>
    </section>
</div>
