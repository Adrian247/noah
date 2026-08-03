"""Servicio HTTP del modelo predictivo.

Contrato con Laravel (`App\\Services\\Predictive\\PredictionServiceClient`):

    POST /predict  {horizon_days, features: {asset_id: {...}}, heuristic: [{asset_id, probability}]}
    → {model_version, predictions: [{asset_id, probability, heuristic_probability}]}

Reglas de diseño: si el modelo no está entrenado o algo sale mal, se responde 503 y Laravel se
queda con su predicción determinística. El servicio nunca es un punto único de falla del producto.
"""

from __future__ import annotations

from typing import Any

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

from .model import RiskModel

app = FastAPI(title="Phoenix Predict", version="1.0.0")

model = RiskModel()


class HeuristicPrediction(BaseModel):
    asset_id: int
    probability: float


class PredictRequest(BaseModel):
    horizon_days: int = 14
    # Mapa de asset_id (como string, tal cual lo serializa PHP) a su vector de características.
    features: dict[str, dict[str, Any]] = Field(default_factory=dict)
    heuristic: list[HeuristicPrediction] = Field(default_factory=list)


@app.get("/health")
def health() -> dict[str, Any]:
    return {
        "status": "ok" if model.available else "sin_modelo",
        "model_available": model.available,
        "model_version": model.version,
        "metrics": model.metrics,
    }


@app.post("/predict")
def predict(request: PredictRequest) -> dict[str, Any]:
    if not model.available:
        raise HTTPException(status_code=503, detail="El modelo no está entrenado todavía.")

    asset_ids = [prediction.asset_id for prediction in request.heuristic]
    if not asset_ids:
        asset_ids = [int(key) for key in request.features]

    rows = []
    resolved: list[int] = []
    for asset_id in asset_ids:
        row = request.features.get(str(asset_id)) or request.features.get(asset_id)  # type: ignore[arg-type]
        if row is None:
            continue
        rows.append(row)
        resolved.append(asset_id)

    if not rows:
        raise HTTPException(status_code=422, detail="No llegaron características para ningún activo.")

    try:
        probabilities = model.predict(rows)
    except RuntimeError as error:
        raise HTTPException(status_code=503, detail=str(error)) from error

    heuristic_by_asset = {p.asset_id: p.probability for p in request.heuristic}

    return {
        "model_version": model.version,
        "horizon_days": request.horizon_days,
        "predictions": [
            {
                "asset_id": asset_id,
                "probability": round(probability, 4),
                "heuristic_probability": heuristic_by_asset.get(asset_id),
            }
            for asset_id, probability in zip(resolved, probabilities)
        ],
    }
