from __future__ import annotations

import time
from typing import Any


def interpretar_mock(contexto: dict[str, Any]) -> dict[str, Any]:
    """
    Narrativa clínica local (Fase 0). Não altera score/classificação CatBoost.
    """
    inicio = time.perf_counter()
    pred = contexto.get("predicao_catboost") or {}
    risco = str(pred.get("classificacao_risco") or "indeterminado")
    probabilidade = pred.get("probabilidade_risco")
    pct = _format_pct(probabilidade)
    shap = pred.get("shap") if isinstance(pred.get("shap"), list) else []
    fatores = _fatores_de_shap(shap)
    deltas = _deltas_texto(contexto.get("deltas_numericos") or {})
    eco = bool(pred.get("recomenda_ecocardiograma"))
    prioridade_eco = pred.get("prioridade_ecocardiograma")
    idade = (contexto.get("consulta_atual") or {}).get("idade_gestacional")
    ig = f" com {idade} semanas" if idade not in (None, "") else ""

    resumo_medico = (
        f"Interpretação automática (mock) da predição CatBoost{ig}. "
        f"Classificação de risco: {risco}"
        + (f" (probabilidade {pct})." if pct else ".")
        + " O escore do modelo não foi recalculado nesta etapa."
    )
    if fatores:
        destaques = ", ".join(f["feature"] for f in fatores[:3])
        resumo_medico += f" Principais contribuições SHAP: {destaques}."
    if eco:
        resumo_medico += (
            " Ecocardiograma fetal recomendado"
            + (f" (prioridade {prioridade_eco})." if prioridade_eco else ".")
        )

    resumo_gestante = (
        "Olá. Sua avaliação de pré-natal foi registrada pela equipe. "
        "O resultado numérico do modelo permanece o mesmo; esta mensagem só explica o contexto clínico. "
        "Compareça às consultas e converse com seu médico sobre os próximos passos."
    )

    recomendacoes = [
        "Manter o acompanhamento pré-natal conforme a agenda da equipe.",
        "Não alterar conduta com base apenas nesta narrativa — o CatBoost é a fonte do risco.",
    ]
    if eco:
        recomendacoes.append("Discutir a indicação de ecocardiograma fetal com a equipe responsável.")

    latency_ms = round((time.perf_counter() - inicio) * 1000, 2)

    return {
        "resumo_medico": resumo_medico,
        "resumo_gestante": resumo_gestante,
        "fatores_explicados": fatores,
        "recomendacoes": recomendacoes,
        "deltas": deltas,
        "predicao_ecoada": {
            "probabilidade_risco": probabilidade,
            "classificacao_risco": risco,
        },
        "model_id": "mock-fase0",
        "tokens_in": 0,
        "tokens_out": 0,
        "latency_ms": latency_ms,
        "mock": True,
        "cache_hit": False,
    }


def _format_pct(value: Any) -> str | None:
    try:
        if value is None:
            return None
        return f"{float(value) * 100:.1f}%"
    except (TypeError, ValueError):
        return None


def _fatores_de_shap(shap: list[Any]) -> list[dict[str, Any]]:
    itens: list[dict[str, Any]] = []
    for raw in shap[:8]:
        if not isinstance(raw, dict):
            continue
        feature = str(raw.get("feature") or raw.get("nome") or raw.get("variavel") or "").strip()
        if feature == "":
            continue
        impacto = raw.get("impact", raw.get("impacto"))
        sinal = ""
        try:
            if impacto is not None:
                sinal = "aumento do risco" if float(impacto) >= 0 else "redução do risco"
        except (TypeError, ValueError):
            sinal = ""
        itens.append(
            {
                "feature": feature,
                "impacto": impacto,
                "explicacao": (
                    f"Contribuição SHAP associada a {sinal}."
                    if sinal
                    else "Contribuição SHAP desta variável na predição."
                ),
            }
        )
    return itens


def _deltas_texto(deltas: Any) -> list[str]:
    if not isinstance(deltas, dict):
        return []
    out: list[str] = []
    for chave, valor in deltas.items():
        if valor in (None, "", []):
            continue
        rotulo = str(chave).replace("_", " ")
        out.append(f"{rotulo}: {valor}")
        if len(out) >= 6:
            break
    return out
