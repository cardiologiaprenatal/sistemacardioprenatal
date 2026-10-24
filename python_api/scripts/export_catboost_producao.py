"""Exporta o CatBoost de produção (experimento_3_completo + class_weight).

Não é chamado na inferência. Rode uma vez para gerar models/model.pkl:

    python -m scripts.export_catboost_producao
"""

from __future__ import annotations

import json
import sys
from pathlib import Path

import joblib
import pandas as pd
from catboost import CatBoostClassifier
from sklearn.compose import ColumnTransformer
from sklearn.impute import SimpleImputer
from sklearn.model_selection import train_test_split
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder, StandardScaler

ROOT = Path(__file__).resolve().parents[1]
if str(ROOT) not in sys.path:
    sys.path.insert(0, str(ROOT))

from app.features import CATEGORICAL_COLS, FULL_FEATURE_COLS  # noqa: E402

RANDOM_STATE = 42
TEST_SIZE = 0.20
TARGET_COL = "cardiopatia_congenita"
N_EST = 80


def _dataset_path() -> Path:
    candidates = [
        ROOT / "data" / "dataset.csv",
        Path("/app/data/dataset.csv"),
    ]
    for parent in ROOT.resolve().parents:
        sibling = parent / "AnalisePython" / "data" / "dataset.csv"
        candidates.append(sibling)
    for path in candidates:
        if path.is_file():
            return path
    raise FileNotFoundError(
        "dataset.csv não encontrado. Esperado em python_api/data ou AnalisePython/data."
    )


def _build_pipeline() -> Pipeline:
    numeric_cols = [c for c in FULL_FEATURE_COLS if c not in CATEGORICAL_COLS]
    categorical_cols = [c for c in FULL_FEATURE_COLS if c in CATEGORICAL_COLS]
    preprocessor = ColumnTransformer(
        transformers=[
            (
                "num",
                Pipeline(
                    steps=[
                        ("imputer", SimpleImputer(strategy="median")),
                        ("scaler", StandardScaler()),
                    ]
                ),
                numeric_cols,
            ),
            (
                "cat",
                Pipeline(
                    steps=[
                        ("imputer", SimpleImputer(strategy="most_frequent")),
                        ("encoder", OneHotEncoder(handle_unknown="ignore", sparse_output=False)),
                    ]
                ),
                categorical_cols,
            ),
        ],
        remainder="drop",
    )
    classifier = CatBoostClassifier(
        iterations=N_EST,
        depth=5,
        learning_rate=0.1,
        random_state=RANDOM_STATE,
        auto_class_weights="Balanced",
        verbose=0,
        allow_writing_files=False,
        thread_count=-1,
    )
    return Pipeline([("preprocessor", preprocessor), ("classifier", classifier)])


def main() -> int:
    dataset = _dataset_path()
    df = pd.read_csv(dataset)
    missing = [c for c in FULL_FEATURE_COLS + [TARGET_COL] if c not in df.columns]
    if missing:
        raise SystemExit(f"Dataset sem colunas: {missing}")

    x = df[FULL_FEATURE_COLS].copy()
    y = df[TARGET_COL].astype(int)
    x_train, x_test, y_train, y_test = train_test_split(
        x, y, test_size=TEST_SIZE, stratify=y, random_state=RANDOM_STATE
    )

    pipeline = _build_pipeline()
    pipeline.fit(x_train, y_train)
    proba = pipeline.predict_proba(x_test)[:, 1]
    pred = (proba >= 0.5).astype(int)
    acc = float((pred == y_test.values).mean())

    out_dir = ROOT / "models"
    out_dir.mkdir(parents=True, exist_ok=True)
    pkl_path = out_dir / "model.pkl"
    joblib.dump(pipeline, pkl_path)

    meta = {
        "modelo": "CatBoost",
        "experimento": "experimento_3_completo",
        "balanceamento": "class_weight",
        "framework": "CatBoost",
        "n_features": len(FULL_FEATURE_COLS),
        "features": FULL_FEATURE_COLS,
        "dataset": dataset.as_posix(),
        "n_train": int(len(x_train)),
        "n_test": int(len(x_test)),
        "test_accuracy": round(acc, 4),
    }
    (out_dir / "model_meta.json").write_text(
        json.dumps(meta, indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )

    sibling_models = None
    for parent in ROOT.resolve().parents:
        candidate = parent / "AnalisePython" / "models"
        if candidate.is_dir() or (parent / "AnalisePython").is_dir():
            sibling_models = candidate
            break
    if sibling_models is not None:
        sibling_models.mkdir(parents=True, exist_ok=True)
        joblib.dump(pipeline, sibling_models / "best_model.joblib")

    print(f"CatBoost salvo em {pkl_path}")
    print(f"test_accuracy={acc:.4f} n_features={len(FULL_FEATURE_COLS)} n_train={len(x_train)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
