# Dominio — Predictive (Phoenix)

## Responsabilidad

Estimar riesgo de falla por equipo a partir del **historial de rutinas aplicadas** al activo
y explicar cada estimación con la evidencia que la produjo. Arquitectura:
[ADR-013](../decisions/ADR-013-predictive-maintenance.md). Operación:
[docs/PREDICTIVE_MAINTENANCE.md](../../docs/PREDICTIVE_MAINTENANCE.md).

## Agregados

### PredictiveAlgorithmVersion (plataforma)

Versión semántica del algoritmo (`draft` | `published` | `archived`). Solo las publicadas
son seleccionables por empresas. El entrenamiento resume rutinas validadas de tenants con
`allow_predictive_training_collection`.

### FailureMode (taxonomía, por empresa)

- `code` canónico, `name`, `system` (hidráulico, motor diésel, tren motriz, eléctrico…).
- `equipment_classes`, `severity`, `mean_repair_hours`.
- `typical_symptoms`, `typical_causes`, `monitoring_signals`, `precursor_event_codes`.
- `text_patterns`: cómo aparece en texto libre (corpus de referencia / comentarios).

### FailurePrediction

Resultado fechado: `predicted_on`, `horizon_days`, `probability`, `expected_failures`,
`risk_level`, `drivers`, `features`, `model_kind` / `model_version`,
`predictive_algorithm_version_id`, `feature_source` (`routines`).

### Catálogo OEM (global) ↔ CatalogItem (por empresa)

`OemEquipmentModel`, `OemMaintenancePlan`, `OemMaintenancePlanItem` son globales.
`catalog_items.oem_equipment_model_id` enlaza el catálogo de equipos del tenant al modelo OEM.

### Entidades de corpus de referencia (opcionales)

`EquipmentShiftLog`, `EquipmentFailure`, `EquipmentEvent`, `EquipmentWorkOrder`,
`EquipmentComponentReplacement`, `EquipmentMeasurement`: alimentan enriquecimiento y
regresión del motor cuando se importa un corpus Excel. **No son la fuente de predicción
del producto.**

## Relaciones

- Todo cuelga de **Asset** ([assets.md](assets.md)).
- Las **Rutinas** ([maintenance.md](maintenance.md)) son la fuente primaria de features.
- El asistente consume el dominio por tools ([ai.md](ai.md)).
- **Company**: `allow_predictive_training_collection`, `predictive_algorithm_version_id`.

## Invariantes

- Predicción de flota sin filtro de tags/ids: solo activos con al menos una rutina validada.
- Una predicción es única por activo, fecha, ventana y modo de falla.
- El nivel de riesgo se deriva de `expected_failures`, nunca de la probabilidad sola.
- Entrenamiento multi-empresa: solo rutinas de empresas con opt-in.
- Solo versiones `published` son seleccionables por clientes.
- Movimientos de algoritmo y settings de empresa quedan en auditoría.
