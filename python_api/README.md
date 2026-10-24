# Microsserviço ML (FastAPI)

API **stateless** de Machine Learning do CardioPrenatal.

- Não acessa o MySQL do Laravel
- Não grava dados
- Comunicação apenas via REST

## Portas

| Serviço | Porta |
|---------|-------|
| Laravel (nginx) | **8000** |
| Microsserviço ML | **8010** |

> Não use a porta 8000 para o Python — ela já é do Laravel.

## Endpoints

- `GET /health`
- `GET /model/info`
- `GET /modelos` (catálogo de experimentos; melhor balanceamento por algoritmo)
- `GET /modelos/metricas` (mesmo catálogo; `todas_combinacoes=true` lista todas as combinações)
- `POST /predict`
- `POST /interpretar/gestante` (narrativa mock; não altera o CatBoost)
- `GET /metrics`
- Swagger: `http://localhost:8010/docs`

## Subir local (sem Docker)

```bash
cd python_api
python -m venv .venv
# Windows: .venv\Scripts\activate
pip install -r requirements.txt
uvicorn app.main:app --host 0.0.0.0 --port 8010 --reload
```

## Subir com Docker Compose

Na raiz do monorepo:

```bash
docker compose up -d --build ml
```

No Laravel (container `app`), use:

```env
PYTHON_API_URL=http://ml:8010
PYTHON_API_TIMEOUT=30
```

Se o ML rodar no Windows host e o Laravel no Docker:

```env
PYTHON_API_URL=http://host.docker.internal:8010
```

## Modelo em produção vs catálogo

- **Catálogo** — `data/metricas_modelos.csv` (pipeline AnalisePython). `GET /modelos` lista os algoritmos treinados e as métricas reais.
- **Produção** — `models/model.pkl` + `models/model_meta.json` (qual experimento foi publicado).
- **Heurística** — só na inferência (`POST /predict`) quando o `.pkl` não está carregado. Não substitui o catálogo.

Em Docker, o CSV é montado de `../AnalisePython/data`.

## Contrato `/predict`

Request:

```json
{
  "idade": 32,
  "diabetes": 1,
  "hipertensao": 0,
  "imc": 31
}
```

Response:

```json
{
  "risk": "Alto",
  "probability": 0.91,
  "class": 1,
  "shap": [{"feature": "idade", "impact": 0.18}],
  "execution_time_ms": 1.2
}
```
