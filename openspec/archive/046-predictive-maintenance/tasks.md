# Tasks — 046 Mantenimiento predictivo

## Fase 1 — Espina de datos y catálogos

- [x] Análisis de `Bitácora Planta 4400.xlsm` y `SanMartin - Reporte Diario SEP 09 2020.xlsm`
- [x] Propuesta + [ADR-013](../../decisions/ADR-013-predictive-maintenance.md) (arquitectura predictiva)
- [x] Migración `2026_08_03_120000_predictive_maintenance_tables`
- [x] Modelos Eloquent + relaciones desde `Asset`
- [x] Catálogo de modos de falla (taxonomía multipropósito)
- [x] Catálogo OEM (Epiroc, Sandvik, Metso/Outotec) y planes por intervalo de horas
- [x] Seeder de catálogos de referencia

## Fase 2 — Ingesta

- [x] ETL `extract_logbooks.py` (xlsm → JSON normalizado)
- [x] Comando `phoenix:predictive:ingest` idempotente
- [x] Normalizador de texto libre de falla → modo de falla
- [x] Fixture reducido de datos reales para pruebas

## Fase 3 — Algoritmo

- [x] `FeatureBuilder` (ventanas, tasas de consumo, precursores, cumplimiento PM, RUL)
- [x] `PredictiveFailureEngine` determinístico (hazard + precursores + factores explicativos)
- [x] Persistencia de predicciones y medición posterior de acierto
- [x] Subproyecto `ml/phoenix-predict` (FastAPI + scikit-learn; Keras y Qdrant descartados, ver ADR-013)
- [x] `PredictionServiceClient` con degradación al motor determinístico
- [x] Censura de equipos en reparación y de ventanas sin futuro observable
- [x] Backtest del motor sobre el histórico (`phoenix:predictive:backtest`)

## Fase 4 — Consumo

- [x] Tool `predict_equipment_failures`
- [x] Tool `get_equipment_health`
- [x] Tool `list_failure_modes`
- [x] Registro en `AiToolRegistry` + instrucciones del asistente
- [x] Endpoints `/api/v1/predictive/*`
- [x] Tests unitarios + prueba de regresión con datos reales

## Pendiente (fases siguientes)

- [ ] Entrenamiento programado y versionado de modelos
- [ ] Captura de vibración y análisis de aceite en la app de campo
- [ ] Integración en vivo con SAP PM / Epiroc Fleet+ / Sandvik My Sandvik · OptiMine
- [x] Página web de confiabilidad (`/app/predictive`: predicciones, salud, catálogos)
