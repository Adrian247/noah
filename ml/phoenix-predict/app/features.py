"""Contrato de características entre Laravel y el modelo.

`App\\Services\\Predictive\\FeatureBuilder` produce estas llaves y este módulo las convierte al
vector numérico que consume el modelo. El orden importa: es el que quedó grabado al entrenar, así
que las columnas nuevas se agregan **al final** y nunca se reordena la lista.
"""

from __future__ import annotations

from typing import Any, Mapping

# Llaves numéricas directas. Ausente o nulo se codifica con 0.0 más una bandera de faltante para
# las que sí distinguen "no hay dato" de "vale cero" (p. ej. MTBF sin fallas registradas).
NUMERIC_FEATURES: list[str] = [
    "shifts_7d",
    "shifts_30d",
    "shifts_90d",
    "shifts_total",
    "worked_hours_7d",
    "worked_hours_30d",
    "worked_hours_90d",
    "worked_hours_total",
    "scheduled_hours_30d",
    "preventive_hours_30d",
    "preventive_hours_90d",
    "corrective_hours_7d",
    "corrective_hours_30d",
    "corrective_hours_90d",
    "operative_fail_hours_30d",
    "standby_hours_30d",
    "availability_7d",
    "availability_30d",
    "availability_90d",
    "utilization_7d",
    "utilization_30d",
    "utilization_90d",
    "corrective_ratio_30d",
    "daily_operating_hours",
    "history_days",
    "days_since_last_log",
    "failures_30d",
    "failures_90d",
    "failures_total",
    "failure_downtime_30d",
    "failure_downtime_90d",
    "pm_backlog_90d",
    "work_orders_executed_90d",
    "work_orders_skipped_90d",
    "alarms_7d",
    "alarms_30d",
    "warnings_7d",
    "warnings_30d",
    "messages_7d",
    "messages_30d",
]

# Numéricas donde el nulo es informativo: se agrega una columna `<nombre>_missing`.
NULLABLE_FEATURES: list[str] = [
    "hour_meter",
    "mtbf_hours",
    "mttr_hours",
    "hours_since_last_failure",
    "hours_since_last_preventive",
    "days_since_last_failure",
    "days_since_last_preventive",
    "pm_compliance_90d",
    "oem_service_interval_hours",
    "oil_per_hour_7d",
    "oil_per_hour_30d",
    "oil_per_hour_90d",
    "diesel_per_hour_7d",
    "diesel_per_hour_30d",
    "diesel_per_hour_90d",
    "oil_rate_ratio",
    "diesel_rate_ratio",
    "coolant_rate_ratio",
    "availability_trend",
    "worst_component_life_used",
]

# Derivadas que el modelo no puede inferir solo con las columnas anteriores.
DERIVED_FEATURES: list[str] = [
    "service_overdue_ratio",
    "distinct_event_codes_7d",
    "recurring_event_codes_7d",
    "distinct_failure_modes",
    "top_failure_mode_share",
]


def feature_names() -> list[str]:
    """Nombres de columna en el orden exacto del vector."""
    names = list(NUMERIC_FEATURES)
    for key in NULLABLE_FEATURES:
        names.append(key)
        names.append(f"{key}_missing")
    names.extend(DERIVED_FEATURES)
    return names


def _number(value: Any) -> float:
    if value is None or value is True or value is False:
        return 0.0
    try:
        return float(value)
    except (TypeError, ValueError):
        return 0.0


def _derive(row: Mapping[str, Any]) -> dict[str, float]:
    interval = row.get("oem_service_interval_hours") or 0
    since_preventive = row.get("hours_since_last_preventive")
    # Cuánto se pasó del intervalo del fabricante: 1.0 es "justo en el servicio", 2.0 es el doble.
    overdue = (
        _number(since_preventive) / float(interval)
        if interval and since_preventive is not None
        else 0.0
    )

    events = row.get("event_codes_7d") or {}
    recurring = sum(1 for event in events.values() if _number(event.get("days")) >= 3)

    history = row.get("failure_modes_history") or {}
    counts = [_number(entry.get("count")) for entry in history.values()]
    total = sum(counts)

    return {
        "service_overdue_ratio": round(overdue, 4),
        "distinct_event_codes_7d": float(len(events)),
        "recurring_event_codes_7d": float(recurring),
        "distinct_failure_modes": float(len(history)),
        "top_failure_mode_share": round(max(counts) / total, 4) if total > 0 else 0.0,
    }


def vectorize(row: Mapping[str, Any]) -> list[float]:
    """Convierte un mapa de características en el vector numérico del modelo."""
    vector = [_number(row.get(key)) for key in NUMERIC_FEATURES]

    for key in NULLABLE_FEATURES:
        value = row.get(key)
        vector.append(_number(value))
        vector.append(1.0 if value is None else 0.0)

    derived = _derive(row)
    vector.extend(derived[key] for key in DERIVED_FEATURES)

    return vector


def vectorize_many(rows: list[Mapping[str, Any]]) -> list[list[float]]:
    return [vectorize(row) for row in rows]
