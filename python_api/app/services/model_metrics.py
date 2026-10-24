"""Catálogo de experimentos: métricas CV vs teste a partir do CSV do pipeline."""

from __future__ import annotations

import csv
import json
import logging
from pathlib import Path
from typing import Any

logger = logging.getLogger(__name__)

DEFAULT_EXPERIMENT = "experimento_3_completo"


def _ancestor_or_none(path: Path, index: int) -> Path | None:
    try:
        return path.parents[index]
    except IndexError:
        return None


_APP_FILE = Path(__file__).resolve()
_GITHUB_ROOT = _ancestor_or_none(_APP_FILE, 4)

METRICS_CANDIDATES = (
    "data/metricas_modelos.csv",
    "/app/data/metricas_modelos.csv",
    "metricas_modelos.csv",
    str(_GITHUB_ROOT / "AnalisePython" / "data" / "metricas_modelos.csv") if _GITHUB_ROOT else "",
)

META_CANDIDATES = (
    "models/model_meta.json",
    "/app/models/model_meta.json",
    str(_GITHUB_ROOT / "AnalisePython" / "models" / "model_meta.json") if _GITHUB_ROOT else "",
)

DISPLAY_NAMES: dict[str, str] = {
    "Logistic Regression": "Regressão Logística",
    "Decision Tree": "Decision Tree",
    "Random Forest": "Random Forest",
    "XGBoost": "XGBoost",
    "LightGBM": "LightGBM",
    "CatBoost": "CatBoost",
    "SVM": "SVM",
    "KNN": "KNN",
    "Naive Bayes": "Naive Bayes",
    "MLP Neural Network": "MLP (Rede Neural)",
    "Extra Trees": "Extra Trees",
    "Gradient Boosting": "Gradient Boosting",
}

MODEL_ORDER = list(DISPLAY_NAMES.keys())

FRAMEWORK_TO_MODELO: dict[str, str] = {
    "catboost": "CatBoost",
    "catboostclassifier": "CatBoost",
    "xgboost": "XGBoost",
    "xgbclassifier": "XGBoost",
    "lightgbm": "LightGBM",
    "lgbmclassifier": "LightGBM",
    "randomforest": "Random Forest",
    "randomforestclassifier": "Random Forest",
    "extratrees": "Extra Trees",
    "extratreesclassifier": "Extra Trees",
    "gradientboosting": "Gradient Boosting",
    "gradientboostingclassifier": "Gradient Boosting",
    "logisticregression": "Logistic Regression",
    "decisiontree": "Decision Tree",
    "decisiontreeclassifier": "Decision Tree",
    "svc": "SVM",
    "svm": "SVM",
    "kneighborsclassifier": "KNN",
    "knn": "KNN",
    "gaussiannb": "Naive Bayes",
    "naivebayes": "Naive Bayes",
    "mlpclassifier": "MLP Neural Network",
    "mlp": "MLP Neural Network",
}


def _f(row: dict[str, str], key: str) -> float | None:
    raw = row.get(key)
    if raw is None or raw == "":
        return None
    try:
        return round(float(raw), 4)
    except ValueError:
        return None


def _pack_split(row: dict[str, str], *, prefix: str) -> dict[str, float | None]:
    if prefix == "test_":
        return {
            "acuracia": _f(row, "test_accuracy"),
            "f1": _f(row, "test_f1_score"),
            "roc_auc": _f(row, "test_roc_auc"),
            "sensibilidade": _f(row, "test_sensibilidade"),
            "especificidade": _f(row, "test_especificidade"),
            "precisao": _f(row, "test_precision"),
            "pr_auc": _f(row, "test_pr_auc"),
        }
    return {
        "acuracia": _f(row, "accuracy_mean"),
        "f1": _f(row, "f1_score_mean"),
        "roc_auc": _f(row, "roc_auc_mean"),
        "sensibilidade": _f(row, "sensibilidade_mean"),
        "especificidade": _f(row, "especificidade_mean"),
        "precisao": _f(row, "precision_mean"),
        "pr_auc": _f(row, "pr_auc_mean"),
    }


def framework_to_modelo(framework: str | None) -> str | None:
    if not framework:
        return None
    key = framework.strip().lower().replace(" ", "").replace("_", "")
    if key in {"heuristic", "heuristica", "heurística"}:
        return None
    return FRAMEWORK_TO_MODELO.get(key)


def load_model_meta(configured: str | None = None) -> dict[str, Any]:
    candidates = [Path(configured)] if configured else []
    candidates.extend(Path(p) for p in META_CANDIDATES if p)
    for path in candidates:
        if path.is_file():
            try:
                data = json.loads(path.read_text(encoding="utf-8"))
            except (OSError, json.JSONDecodeError):
                continue
            if isinstance(data, dict) and data.get("modelo"):
                return data
    return {}


class ModelMetricsService:
    def __init__(self) -> None:
        self.path: str = ""
        self._rows: list[dict[str, str]] = []
        self._cache: dict[tuple[str, bool], dict[str, Any]] = {}

    @property
    def loaded(self) -> bool:
        return bool(self._rows)

    def resolve_path(self, configured: str) -> Path:
        ordered = [Path(configured), *[Path(p) for p in METRICS_CANDIDATES if p]]
        for candidate in ordered:
            if candidate.is_file():
                return candidate
        return Path(configured)

    def load(self, configured_path: str) -> None:
        path = self.resolve_path(configured_path)
        self.path = path.as_posix()
        self._cache.clear()
        if not path.is_file():
            logger.warning("metrics_csv_missing path=%s", self.path)
            self._rows = []
            return
        with path.open(newline="", encoding="utf-8") as handle:
            self._rows = list(csv.DictReader(handle))
        logger.info("metrics_csv_loaded path=%s rows=%s", self.path, len(self._rows))

    def listar_experimentos(self) -> list[str]:
        seen: list[str] = []
        for row in self._rows:
            exp = row.get("experimento") or ""
            if exp and exp not in seen:
                seen.append(exp)
        return seen

    def comparacao(
        self,
        experimento: str = DEFAULT_EXPERIMENT,
        *,
        apenas_melhor_por_modelo: bool = True,
    ) -> dict[str, Any]:
        key = (experimento, apenas_melhor_por_modelo)
        cached = self._cache.get(key)
        if cached is not None:
            return cached
        result = self._comparacao_impl(experimento, apenas_melhor_por_modelo)
        self._cache[key] = result
        return result

    def _comparacao_impl(
        self,
        experimento: str,
        apenas_melhor_por_modelo: bool,
    ) -> dict[str, Any]:
        if not self._rows:
            return {
                "disponivel": False,
                "experimento": experimento,
                "fonte": self.path,
                "total_modelos": 0,
                "modelos": [],
                "destaques": {},
                "mensagem": "Arquivo data/metricas_modelos.csv não encontrado.",
            }

        subset = [r for r in self._rows if (r.get("experimento") or "") == experimento]
        if not subset:
            return {
                "disponivel": False,
                "experimento": experimento,
                "fonte": self.path,
                "experimentos_disponiveis": self.listar_experimentos(),
                "total_modelos": 0,
                "modelos": [],
                "destaques": {},
                "mensagem": f"Experimento '{experimento}' não encontrado.",
            }

        if apenas_melhor_por_modelo:
            best_by_model: dict[str, dict[str, str]] = {}
            for row in subset:
                modelo = row.get("modelo") or ""
                f1 = _f(row, "f1_score_mean") or -1.0
                prev = best_by_model.get(modelo)
                prev_f1 = _f(prev, "f1_score_mean") if prev else -1.0
                if prev is None or f1 > (prev_f1 or -1.0):
                    best_by_model[modelo] = row
            selected = list(best_by_model.values())
        else:
            selected = subset

        def sort_key(row: dict[str, str]) -> tuple[int, str]:
            nome = row.get("modelo") or ""
            try:
                return (MODEL_ORDER.index(nome), nome)
            except ValueError:
                return (999, nome)

        selected.sort(key=sort_key)

        modelos: list[dict[str, Any]] = []
        for row in selected:
            nome = row.get("modelo") or ""
            modelos.append({
                "modelo": nome,
                "nome_exibicao": DISPLAY_NAMES.get(nome, nome),
                "balanceamento": row.get("balanceamento"),
                "status": None,
                "treino_cv": _pack_split(row, prefix=""),
                "teste": _pack_split(row, prefix="test_"),
            })

        destaques = self._destaques(modelos)
        return {
            "disponivel": True,
            "experimento": experimento,
            "fonte": self.path,
            "criterio_selecao": "melhor_f1_cv" if apenas_melhor_por_modelo else "todas_combinacoes",
            "total_modelos": len(modelos),
            "destaques": destaques,
            "modelos": modelos,
            "legendas": {
                "treino_cv": "Média Stratified 10-Fold Cross-Validation",
                "teste": "Hold-out 20% (conjunto de teste)",
                "acuracia": "Cuidado: com CCF ~7%, acurácia alta pode mascarar baixa sensibilidade",
            },
            "mensagem": None,
        }

    def anotar_producao(
        self,
        catalogo: dict[str, Any],
        *,
        loaded: bool,
        framework: str | None,
        model_path: str | None,
        meta: dict[str, Any] | None = None,
    ) -> dict[str, Any]:
        """Marca o modelo publicado sem misturar heurística no catálogo de experimentos."""
        meta = meta or {}
        publicado = (meta.get("modelo") or framework_to_modelo(framework) or "").strip()
        balanceamento_pub = (meta.get("balanceamento") or "").strip() or None

        marcado = False
        if publicado and balanceamento_pub:
            for item in catalogo.get("modelos") or []:
                if item.get("modelo") == publicado and item.get("balanceamento") == balanceamento_pub:
                    item["status"] = "produção" if loaded else "publicado"
                    marcado = True
                    break
        if publicado and not marcado:
            for item in catalogo.get("modelos") or []:
                if item.get("modelo") == publicado:
                    item["status"] = "produção" if loaded else "publicado"
                    break

        catalogo["producao"] = {
            "modelo": publicado or None,
            "nome_exibicao": DISPLAY_NAMES.get(publicado, publicado) if publicado else None,
            "balanceamento": balanceamento_pub,
            "carregado": loaded,
            "caminho": model_path,
            "framework": framework,
            "modo_inferencia": "modelo" if loaded else "heuristica",
            "mensagem": (
                None
                if loaded
                else (
                    "Modelo publicado sem artefato .pkl; a inferência usa regras clínicas "
                    "até o arquivo ser carregado. O catálogo de experimentos permanece intacto."
                    if publicado
                    else "Nenhum modelo de ML publicado; a predição usa regras clínicas determinísticas."
                )
            ),
        }
        return catalogo

    @staticmethod
    def _destaques(modelos: list[dict[str, Any]]) -> dict[str, Any]:
        if not modelos:
            return {}

        def pick(path: tuple[str, str], higher: bool = True) -> dict[str, Any]:
            best = None
            best_val = None
            for m in modelos:
                split = m.get(path[0]) or {}
                val = split.get(path[1])
                if val is None:
                    continue
                if best_val is None or (higher and val > best_val) or (not higher and val < best_val):
                    best_val = val
                    best = m
            if best is None:
                return {}
            return {
                "modelo": best["modelo"],
                "nome_exibicao": best["nome_exibicao"],
                "valor": best_val,
                "balanceamento": best.get("balanceamento"),
            }

        return {
            "melhor_f1_cv": pick(("treino_cv", "f1")),
            "melhor_f1_teste": pick(("teste", "f1")),
            "melhor_roc_auc_teste": pick(("teste", "roc_auc")),
            "maior_acuracia_teste": pick(("teste", "acuracia")),
        }
