from __future__ import annotations

import logging
from contextlib import asynccontextmanager
from typing import Any

from fastapi import FastAPI, HTTPException, Query, Request

from app.config import get_settings
from app.features import FULL_FEATURE_COLS
from app.model_loader import load_model
from app.schemas import (
    HealthResponse,
    MetricsResponse,
    ModelInfoResponse,
    ModelosComparacaoResponse,
    PredictRequest,
    PredictResponse,
)
from app.services.interpreter import interpretar_mock
from app.services.model_metrics import DEFAULT_EXPERIMENT, ModelMetricsService, load_model_meta
from app.services.predictor import PredictorService

logger = logging.getLogger(__name__)
settings = get_settings()


@asynccontextmanager
async def lifespan(app: FastAPI):
    logging.basicConfig(level=settings.log_level.upper())
    meta = load_model_meta(settings.model_meta_path)
    model = load_model(settings.model_path)
    app.state.predictor = PredictorService(
        model=model,
        experimento=str(meta.get("experimento") or DEFAULT_EXPERIMENT),
        modelo_producao=str(meta.get("modelo") or "CatBoost"),
    )

    metrics_service = ModelMetricsService()
    metrics_service.load(settings.metrics_path)
    app.state.model_metrics = metrics_service
    app.state.model_meta = meta
    if not metrics_service.loaded:
        logger.warning(
            "Catálogo de experimentos ausente em %s — GET /modelos não listará os modelos treinados.",
            settings.metrics_path,
        )
    logger.info(
        "Microsserviço ML iniciado | loaded=%s | framework=%s | path=%s | catalogo_linhas=%s",
        model.loaded,
        model.framework,
        model.path,
        len(metrics_service._rows),
    )
    yield


app = FastAPI(
    title=settings.app_name,
    version=settings.app_version,
    description=(
        "Microsserviço de Machine Learning do CardioPrenatal. "
        "Infere com CatBoost já treinado (experimento_3_completo). "
        "Não treina a cada gestante. Heurística só se o artefato falhar."
    ),
    lifespan=lifespan,
)


def _predictor(request: Request) -> PredictorService:
    return request.app.state.predictor


def _model_metrics(request: Request) -> ModelMetricsService:
    return request.app.state.model_metrics


def _catalogo_modelos(
    request: Request,
    experimento: str,
    *,
    apenas_melhor_por_modelo: bool,
) -> dict[str, Any]:
    predictor = _predictor(request)
    model = predictor.model
    catalogo = _model_metrics(request).comparacao(
        experimento,
        apenas_melhor_por_modelo=apenas_melhor_por_modelo,
    )
    return _model_metrics(request).anotar_producao(
        catalogo,
        loaded=bool(model.loaded),
        framework=model.framework,
        model_path=model.path,
        meta=getattr(request.app.state, "model_meta", {}) or {},
    )


@app.get("/health", response_model=HealthResponse, tags=["ops"])
def health() -> HealthResponse:
    return HealthResponse(status="ok")


@app.get("/model/info", response_model=ModelInfoResponse, tags=["model"])
def model_info(request: Request) -> ModelInfoResponse:
    predictor = _predictor(request)
    model = predictor.model
    meta = getattr(request.app.state, "model_meta", {}) or {}
    features = model.features or list(FULL_FEATURE_COLS)
    loaded = bool(model.loaded)
    return ModelInfoResponse(
        name=settings.app_name,
        version=settings.app_version,
        loaded=loaded,
        model_path=model.path,
        features=features,
        framework=model.framework if loaded else "heuristic",
        modelo=str(meta.get("modelo") or predictor.modelo_producao) if loaded else "Heurística",
        experimento=str(meta.get("experimento") or predictor.experimento) if loaded else None,
        balanceamento=meta.get("balanceamento") if loaded else None,
        modo=model.mode,
        n_features=len(features),
        load_error=model.load_error,
    )


@app.get("/modelos", response_model=ModelosComparacaoResponse, tags=["model"])
def listar_modelos(
    request: Request,
    experimento: str = Query(DEFAULT_EXPERIMENT),
) -> dict[str, Any]:
    """
    Catálogo de experimentos (melhor balanceamento por algoritmo).
    Distinto do modelo publicado em produção e da heurística de inferência.
    """
    return _catalogo_modelos(request, experimento, apenas_melhor_por_modelo=True)


@app.get("/modelos/metricas", response_model=ModelosComparacaoResponse, tags=["model"])
def metricas_modelos_detalhadas(
    request: Request,
    experimento: str = Query(DEFAULT_EXPERIMENT),
    todas_combinacoes: bool = Query(False),
) -> dict[str, Any]:
    """Catálogo completo; `todas_combinacoes=true` lista cada par modelo×balanceamento."""
    return _catalogo_modelos(
        request,
        experimento,
        apenas_melhor_por_modelo=not todas_combinacoes,
    )


@app.post("/predict", response_model=PredictResponse, tags=["inference"])
def predict(body: PredictRequest, request: Request) -> PredictResponse:
    predictor = _predictor(request)
    payload: dict[str, Any] = body.model_dump(exclude_none=False)
    payload.update(body.model_extra or {})

    try:
        return predictor.predict(payload)
    except Exception as exc:  # noqa: BLE001
        logger.exception("Erro na predição: %s", exc)
        raise HTTPException(status_code=500, detail="Falha ao executar predição.") from exc


@app.post("/interpretar/gestante", tags=["interpretacao"])
def interpretar_gestante(body: dict[str, Any]) -> dict[str, Any]:
    """
    Narrativa clínica (mock Fase 0). Não altera probabilidade nem classificação CatBoost.
    """
    try:
        return interpretar_mock(body if isinstance(body, dict) else {})
    except Exception as exc:  # noqa: BLE001
        logger.exception("Erro na interpretação: %s", exc)
        raise HTTPException(status_code=500, detail="Falha ao gerar interpretação.") from exc


@app.get("/metrics", response_model=MetricsResponse, tags=["ops"])
def metrics(request: Request) -> MetricsResponse:
    predictor = _predictor(request)
    m = predictor.metrics
    return MetricsResponse(
        model_loaded=predictor.model.loaded,
        total_predictions=m.total_predictions,
        avg_execution_time_ms=m.avg_execution_time_ms,
        last_prediction_at=m.last_prediction_at,
        extra={
            "framework": predictor.model.framework,
            "features": predictor.model.features,
            "metrics_loaded": _model_metrics(request).loaded,
            "fonte_catboost": m.catboost_predictions,
            "fonte_heuristica": m.heuristic_predictions,
            "load_error": predictor.model.load_error,
        },
    )
