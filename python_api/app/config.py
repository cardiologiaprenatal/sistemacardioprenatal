from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    """Configuração do microsserviço via variáveis de ambiente."""

    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        extra="ignore",
    )

    app_name: str = "CardioPrenatal-ML"
    app_version: str = "1.0.0"
    host: str = "0.0.0.0"
    port: int = 8010
    model_path: str = "models/model.pkl"
    metrics_path: str = "data/metricas_modelos.csv"
    model_meta_path: str = "models/model_meta.json"
    log_level: str = "info"


@lru_cache
def get_settings() -> Settings:
    return Settings()
