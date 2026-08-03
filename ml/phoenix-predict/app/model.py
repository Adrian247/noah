"""Entrenamiento y carga del modelo de riesgo de falla.

El modelo estima P(al menos una falla correctiva en la ventana) a partir del mismo vector de
características que usa el motor determinístico de PHP. Se entrena con validación temporal: los
cortes más recientes se reservan para medir, porque una partición aleatoria filtraría el futuro
dentro del entrenamiento y daría un AUC inflado.
"""

from __future__ import annotations

import json
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Mapping

import joblib
import numpy as np
from sklearn.calibration import CalibratedClassifierCV
from sklearn.ensemble import HistGradientBoostingClassifier
from sklearn.metrics import average_precision_score, brier_score_loss, roc_auc_score

from .features import feature_names, vectorize_many

MODEL_PATH = Path(__file__).resolve().parent.parent / "artifacts" / "risk-model.joblib"

MODEL_VERSION_PREFIX = "gbdt"


@dataclass
class TrainingReport:
    version: str
    rows: int
    positives: int
    features: int
    holdout_rows: int
    metrics: dict[str, float | None] = field(default_factory=dict)
    top_features: list[tuple[str, float]] = field(default_factory=list)

    def to_dict(self) -> dict[str, Any]:
        return {
            "version": self.version,
            "rows": self.rows,
            "positives": self.positives,
            "features": self.features,
            "holdout_rows": self.holdout_rows,
            "metrics": self.metrics,
            "top_features": [{"name": name, "importance": value} for name, value in self.top_features],
        }


def _split_by_date(rows: list[Mapping[str, Any]], holdout_fraction: float) -> tuple[list, list]:
    """Corte temporal: el holdout son las fechas más recientes, nunca filas al azar."""
    ordered = sorted(rows, key=lambda row: str(row.get("as_of") or ""))
    if len(ordered) < 40:
        return ordered, []

    cut = int(len(ordered) * (1 - holdout_fraction))
    boundary = str(ordered[cut].get("as_of") or "")
    # No se parte un mismo día entre train y holdout.
    train = [row for row in ordered if str(row.get("as_of") or "") < boundary]
    holdout = [row for row in ordered if str(row.get("as_of") or "") >= boundary]

    return (train, holdout) if train and holdout else (ordered, [])


def _build_estimator(positives: int) -> Any:
    base = HistGradientBoostingClassifier(
        max_iter=180,
        learning_rate=0.06,
        max_depth=4,
        min_samples_leaf=12,
        l2_regularization=1.0,
        random_state=1337,
    )

    # La probabilidad tiene que ser usable como número, no solo como ranking: se calibra si hay
    # suficientes positivos para que la validación cruzada interna tenga sentido.
    if positives >= 60:
        return CalibratedClassifierCV(base, method="isotonic", cv=3)

    return base


def train(dataset: Mapping[str, Any], holdout_fraction: float = 0.25) -> TrainingReport:
    rows = list(dataset.get("rows") or [])
    if not rows:
        raise ValueError("El dataset viene vacío.")

    labels_all = [int(row.get("label", 0)) for row in rows]
    if len(set(labels_all)) < 2:
        raise ValueError("El dataset tiene una sola clase; amplía la ventana o el histórico.")

    train_rows, holdout_rows = _split_by_date(rows, holdout_fraction)

    x_train = np.asarray(vectorize_many(train_rows), dtype=float)
    y_train = np.asarray([int(row.get("label", 0)) for row in train_rows], dtype=int)

    if len(set(y_train.tolist())) < 2:
        raise ValueError("El tramo de entrenamiento quedó con una sola clase; reduce holdout_fraction.")

    estimator = _build_estimator(int(y_train.sum()))
    estimator.fit(x_train, y_train)

    metrics: dict[str, float | None] = {}
    if holdout_rows:
        x_holdout = np.asarray(vectorize_many(holdout_rows), dtype=float)
        y_holdout = np.asarray([int(row.get("label", 0)) for row in holdout_rows], dtype=int)
        probabilities = estimator.predict_proba(x_holdout)[:, 1]

        if len(set(y_holdout.tolist())) > 1:
            metrics["roc_auc"] = round(float(roc_auc_score(y_holdout, probabilities)), 4)
            metrics["average_precision"] = round(float(average_precision_score(y_holdout, probabilities)), 4)
        metrics["brier"] = round(float(brier_score_loss(y_holdout, probabilities)), 4)
        metrics["holdout_positive_rate"] = round(float(y_holdout.mean()), 4)

    horizon = int(dataset.get("horizon_days") or 14)
    version = f"{MODEL_VERSION_PREFIX}-h{horizon}-n{len(train_rows)}"

    MODEL_PATH.parent.mkdir(parents=True, exist_ok=True)
    joblib.dump(
        {
            "estimator": estimator,
            "feature_names": feature_names(),
            "version": version,
            "horizon_days": horizon,
            "metrics": metrics,
        },
        MODEL_PATH,
    )

    return TrainingReport(
        version=version,
        rows=len(rows),
        positives=sum(labels_all),
        features=len(feature_names()),
        holdout_rows=len(holdout_rows),
        metrics=metrics,
        top_features=_permutation_importance(estimator, x_train, y_train),
    )


def _permutation_importance(estimator: Any, x: np.ndarray, y: np.ndarray) -> list[tuple[str, float]]:
    """Importancia por permutación: sirve igual con el modelo calibrado, que no expone ganancias."""
    from sklearn.inspection import permutation_importance

    try:
        result = permutation_importance(
            estimator, x, y, n_repeats=5, random_state=1337, scoring="roc_auc"
        )
    except Exception:  # noqa: BLE001 - la importancia es informativa, no debe tumbar el entrenamiento
        return []

    names = feature_names()
    ranked = sorted(zip(names, result.importances_mean), key=lambda pair: pair[1], reverse=True)

    return [(name, round(float(value), 5)) for name, value in ranked[:12] if value > 0]


class RiskModel:
    """Modelo cargado en memoria; recarga sola si el archivo cambia."""

    def __init__(self, path: Path = MODEL_PATH) -> None:
        self.path = path
        self._bundle: dict[str, Any] | None = None
        self._mtime: float | None = None

    @property
    def available(self) -> bool:
        return self.path.is_file()

    def _load(self) -> dict[str, Any] | None:
        if not self.available:
            self._bundle = None
            return None

        mtime = self.path.stat().st_mtime
        if self._bundle is None or self._mtime != mtime:
            self._bundle = joblib.load(self.path)
            self._mtime = mtime

        return self._bundle

    @property
    def version(self) -> str | None:
        bundle = self._load()
        return None if bundle is None else str(bundle.get("version"))

    @property
    def metrics(self) -> dict[str, Any]:
        bundle = self._load()
        return {} if bundle is None else dict(bundle.get("metrics") or {})

    def predict(self, rows: list[Mapping[str, Any]]) -> list[float]:
        bundle = self._load()
        if bundle is None or not rows:
            return []

        expected = bundle.get("feature_names") or []
        current = feature_names()
        if len(expected) != len(current):
            # El contrato cambió después de entrenar: mejor no responder que responder mal.
            raise RuntimeError(
                f"El modelo espera {len(expected)} características y el contrato actual tiene "
                f"{len(current)}. Reentrena con phoenix:predictive:dataset + scripts/train.py."
            )

        matrix = np.asarray(vectorize_many(rows), dtype=float)

        return [float(value) for value in bundle["estimator"].predict_proba(matrix)[:, 1]]


def load_dataset(path: str | Path) -> dict[str, Any]:
    with open(path, encoding="utf-8") as handle:
        return json.load(handle)
