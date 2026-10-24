"""Carregamento do artefato CatBoost (experimento_3_completo). Heurística só se o .pkl falhar."""

from __future__ import annotations

import logging
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any

import joblib

from app.features import FULL_FEATURE_COLS, SLIM_FEATURE_ORDER

logger = logging.getLogger(__name__)

MODEL_CANDIDATES = (
    "models/model.pkl",
    "models/best_model.joblib",
    "models/model.joblib",
    "/app/models/model.pkl",
    "/app/models/best_model.joblib",
)


def _sibling_analisepython_models() -> list[Path]:
    for parent in Path(__file__).resolve().parents:
        base = parent / "AnalisePython" / "models"
        if base.is_dir():
            return [
                base / "best_model.joblib",
                base / "model.pkl",
                base / "model.joblib",
            ]
    return []


@dataclass
class LoadedModel:
    estimator: Any | None
    path: str
    loaded: bool
    framework: str | None = None
    features: list[str] = field(default_factory=lambda: list(FULL_FEATURE_COLS))
    mode: str = "heuristic"  # heuristic | full_pipeline | slim
    load_error: str | None = None


def _inner_estimator(estimator: Any) -> Any:
    named = getattr(estimator, "named_steps", None)
    if isinstance(named, dict):
        return named.get("classifier") or next(reversed(list(named.values())), estimator)
    return estimator


def _framework_name(estimator: Any) -> str:
    inner = _inner_estimator(estimator)
    name = type(inner).__name__
    if name.lower().startswith("catboost"):
        return "CatBoost"
    if name == "Pipeline":
        return type(estimator).__name__
    return name


def _detect_mode(estimator: Any) -> tuple[str, list[str]]:
    named = getattr(estimator, "named_steps", None)
    if isinstance(named, dict):
        pre = named.get("preprocessor") or next(iter(named.values()), None)
        if pre is not None:
            names = getattr(pre, "feature_names_in_", None)
            if names is not None and len(list(names)) >= 20:
                return "full_pipeline", [str(n) for n in list(names)]
            return "full_pipeline", list(FULL_FEATURE_COLS)

    names = getattr(estimator, "feature_names_in_", None)
    if names is not None:
        cols = [str(n) for n in list(names)]
        if len(cols) >= 20:
            return "full_pipeline", cols
        return "slim", cols

    return "slim", list(SLIM_FEATURE_ORDER)


def _resolve_path(configured: str) -> Path:
    path = Path(configured)
    if path.is_file():
        return path

    ordered: list[Path] = [Path(p) for p in MODEL_CANDIDATES]
    ordered.extend(_sibling_analisepython_models())
    for candidate in ordered:
        if candidate.is_file():
            logger.info("model_resolved configured=%s using=%s", configured, candidate.as_posix())
            return candidate
    return path


def load_model(model_path: str) -> LoadedModel:
    path = _resolve_path(model_path)
    path_display = path.as_posix()

    if not path.is_file():
        msg = f"Artefato CatBoost não encontrado em {path_display}"
        logger.warning("%s — inferência usará heurística até o model.pkl ser publicado.", msg)
        return LoadedModel(
            estimator=None,
            path=path_display,
            loaded=False,
            framework="heuristic",
            features=list(FULL_FEATURE_COLS),
            mode="heuristic",
            load_error=msg,
        )

    try:
        estimator = joblib.load(path)
        mode, features = _detect_mode(estimator)
        framework = _framework_name(estimator)
        logger.info(
            "model_loaded path=%s framework=%s mode=%s n_features=%s",
            path_display,
            framework,
            mode,
            len(features),
        )
        return LoadedModel(
            estimator=estimator,
            path=path_display,
            loaded=True,
            framework=framework,
            features=features,
            mode=mode,
        )
    except Exception as exc:  # noqa: BLE001
        msg = f"Falha ao carregar CatBoost ({path_display}): {exc}"
        logger.exception("model_load_failed: %s", exc)
        return LoadedModel(
            estimator=None,
            path=path_display,
            loaded=False,
            framework="heuristic",
            features=list(FULL_FEATURE_COLS),
            mode="heuristic",
            load_error=msg,
        )
