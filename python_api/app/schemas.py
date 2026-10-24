from __future__ import annotations

from typing import Any

from pydantic import BaseModel, ConfigDict, Field, model_validator


class HealthResponse(BaseModel):
    status: str = "ok"


class ModelInfoResponse(BaseModel):
    name: str
    version: str
    loaded: bool
    model_path: str
    features: list[str]
    framework: str | None = None
    modelo: str | None = None
    experimento: str | None = None
    balanceamento: str | None = None
    modo: str | None = None
    n_features: int | None = None
    load_error: str | None = None


class ShapItem(BaseModel):
    feature: str
    impact: float


class PredictRequest(BaseModel):
    """
    Entrada flexível: 36 features do experimento_3_completo + aliases enxutos do Laravel.
    """

    model_config = ConfigDict(extra="allow")

    id_gestante: str | None = None
    idade: float | None = Field(default=None, description="Idade materna (anos)")
    diabetes: int | None = Field(default=None, ge=0, le=1)
    hipertensao: int | None = Field(default=None, ge=0, le=1)
    imc: float | None = Field(default=None, gt=0)
    idade_gestacional: float | None = None
    tabagismo: int | None = Field(default=None, ge=0, le=1)
    paridade: int | None = Field(default=None, ge=0)
    idade_materna: float | None = None
    imc_pre_gestacional: float | None = None
    diabetes_gestacional: int | None = Field(default=None, ge=0, le=1)
    diabetes_pre_gestacional: int | None = Field(default=None, ge=0, le=1)
    hipertensao_cronica: int | None = Field(default=None, ge=0, le=1)


class PredictResponse(BaseModel):
    risk: str
    probability: float = Field(ge=0, le=1)
    class_: int = Field(alias="class", ge=0, le=1)
    shap: list[ShapItem]
    execution_time_ms: float
    classificacao_risco: str | None = None
    probabilidade_risco: float | None = Field(default=None, ge=0, le=1)
    score: float | None = None
    fonte: str = "heuristica"
    modelo: str = "Heurística"
    experimento: str | None = None
    modo_inferencia: str = "heuristica"
    fallback_motivo: str | None = None
    id_gestante: str | None = None

    model_config = ConfigDict(populate_by_name=True, serialize_by_alias=True)

    @model_validator(mode="after")
    def _fill_aliases(self) -> PredictResponse:
        if self.classificacao_risco is None:
            self.classificacao_risco = self.risk
        if self.probabilidade_risco is None:
            self.probabilidade_risco = self.probability
        if self.score is None:
            self.score = self.probability
        return self


class MetricsResponse(BaseModel):
    model_loaded: bool
    total_predictions: int
    avg_execution_time_ms: float | None
    last_prediction_at: str | None
    extra: dict[str, Any] = Field(default_factory=dict)


class MetricSplit(BaseModel):
    acuracia: float | None = None
    f1: float | None = None
    roc_auc: float | None = None
    sensibilidade: float | None = None
    especificidade: float | None = None
    precisao: float | None = None
    pr_auc: float | None = None


class ModeloMetricasItem(BaseModel):
    modelo: str
    nome_exibicao: str
    balanceamento: str | None = None
    status: str | None = None
    treino_cv: MetricSplit
    teste: MetricSplit
    carregado: bool | None = None
    caminho: str | None = None
    features: list[str] | None = None
    operacao: dict[str, Any] | None = None


class ModelosComparacaoResponse(BaseModel):
    disponivel: bool
    experimento: str | None = None
    fonte: str | None = None
    criterio_selecao: str | None = None
    total_modelos: int = 0
    destaques: dict[str, Any] = Field(default_factory=dict)
    modelos: list[ModeloMetricasItem] = Field(default_factory=list)
    legendas: dict[str, str] | list[str] = Field(default_factory=dict)
    mensagem: str | None = None
    experimentos_disponiveis: list[str] | None = None
    producao: dict[str, Any] | None = None
