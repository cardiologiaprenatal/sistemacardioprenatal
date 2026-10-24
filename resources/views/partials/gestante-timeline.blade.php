@php
    use App\Services\GestanteTimelineService;
    /** @var \Illuminate\Support\Collection $timeline */
    $svc = app(GestanteTimelineService::class);
    $timelineWhatsapp = collect($timeline ?? [])->where('tipo', 'whatsapp')->values();
@endphp

<div class="main-card is-collapsed" id="gestanteTimelineCard">
    <div class="card-toolbar">
        <h2 class="card-title" style="margin: 0; font-size: 20px;">Linha do tempo WhatsApp</h2>
        <button type="button"
                class="btn-secondary-outline"
                id="btnToggleTimeline"
                aria-expanded="false"
                aria-controls="gestanteTimelineBody">
            Expandir
        </button>
    </div>

    <div id="gestanteTimelineBody">
        <p class="page-subtitle" style="margin: 0 0 20px;">Mensagens enviadas e recebidas pelo WhatsApp, em ordem cronológica.</p>

        @if ($timelineWhatsapp->isEmpty())
            <p class="dash-empty" style="text-align:left; padding: 8px 0;">Ainda não há mensagens de WhatsApp registradas para esta gestante.</p>
        @else
            <div class="clinical-timeline">
                @foreach ($timelineWhatsapp as $evento)
                    <article class="clinical-timeline-item clinical-timeline-item--{{ $evento['tipo'] }}">
                        <div class="clinical-timeline-marker" aria-hidden="true"></div>
                        <div class="clinical-timeline-body">
                            <div class="clinical-timeline-head">
                                <div>
                                    <span class="clinical-timeline-tipo">{{ $svc->labelTipo($evento['tipo']) }}</span>
                                    <h3 class="clinical-timeline-titulo">{{ $evento['titulo'] }}</h3>
                                    <p class="clinical-timeline-data">{{ $evento['ocorrido_em']->format('d/m/Y H:i') }}</p>
                                </div>
                            </div>
                            @if (! empty($evento['descricao']))
                                <p class="clinical-timeline-desc">{{ $evento['descricao'] }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</div>

<style>
    .clinical-timeline { position: relative; padding-left: 20px; border-left: 2px solid var(--border); }
    .clinical-timeline-item { position: relative; padding-bottom: 20px; }
    .clinical-timeline-item:last-child { padding-bottom: 0; }
    .clinical-timeline-marker {
        position: absolute; left: -26px; top: 4px; width: 12px; height: 12px; border-radius: 50%;
        background: var(--accent); border: 2px solid #fff; box-shadow: 0 0 0 2px var(--border);
    }
    .clinical-timeline-item--whatsapp .clinical-timeline-marker { background: var(--status-info); }
    .clinical-timeline-head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 10px; margin-bottom: 6px; }
    .clinical-timeline-tipo {
        display: inline-block; font-size: 11px; font-weight: 700; letter-spacing: 0.06em;
        text-transform: uppercase; color: var(--muted); margin-bottom: 2px;
    }
    .clinical-timeline-titulo { margin: 0; font-size: 15px; font-weight: 700; color: var(--text); line-height: 1.3; }
    .clinical-timeline-data { margin: 4px 0 0; font-size: 13px; color: var(--muted); }
    .clinical-timeline-desc { margin: 0; font-size: 14px; line-height: 1.5; color: var(--text); }
    #gestanteTimelineCard.is-collapsed #gestanteTimelineBody { display: none; }
    #gestanteTimelineCard.is-collapsed .card-toolbar { margin-bottom: 0; }
</style>

<script>
    (function () {
        const card = document.getElementById('gestanteTimelineCard');
        const btn = document.getElementById('btnToggleTimeline');
        if (!card || !btn) return;

        function aplicar(collapsed) {
            card.classList.toggle('is-collapsed', collapsed);
            btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            btn.textContent = collapsed ? 'Expandir' : 'Minimizar';
        }

        aplicar(true);

        btn.addEventListener('click', function () {
            aplicar(!card.classList.contains('is-collapsed'));
        });
    })();
</script>
