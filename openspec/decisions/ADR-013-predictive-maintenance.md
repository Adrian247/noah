# ADR-013 — Motor de mantenimiento predictivo híbrido

## Estado

Aceptada (actualizada: predicción sobre rutinas aplicadas).

## Contexto

La operación necesita saber a qué equipo entrar antes de que falle. En Phoenix, la fuente
operativa de mantenimiento son las **rutinas aplicadas a activos** (asignadas, ejecutadas y
validadas). Dos bitácoras Excel reales (planta 4400 y mina San Martín) sirvieron solo como
**corpus de referencia** para diseñar features, taxonomía de fallas y pruebas de regresión —
no como carga recurrente del producto.

Restricciones de diseño:

1. **La respuesta no puede depender de un servicio de IA.** Si el modelo ML no está entrenado
   o el servicio está caído, el motor determinístico responde.
2. **El número tiene que ser auditable.** Cada predicción trae `drivers` con evidencia.
3. **El historial por empresa puede ser corto.** Contracción bayesiana (activo → clase →
   empresa) estabiliza tasas con pocos datos.
4. **Gobernanza multi-tenant.** El entrenamiento multi-empresa solo usa rutinas de clientes
   con opt-in explícito; las versiones del algoritmo se publican con semver.

## Decisión

### Fuente de predicción

`FeatureBuilder` toma como **fuente primaria** el historial de rutinas validadas del activo
(frecuencia, duración, consumos, backlog, cumplimiento). Las tablas de bitácora
(`equipment_shift_logs`, etc.) enriquecen si existen (corpus/import), pero **no son
requisito** para predecir.

### Capas

Dos capas sobre el mismo vector de características:

- **Capa determinística en PHP** (`PredictiveFailureEngine`): peligro exponencial + factores
  observables. Siempre responde y explica.
- **Capa ML opcional** (`ml/phoenix-predict`, FastAPI + HistGradientBoosting): refina
  probabilidad cuando está disponible. Si no, se usa solo la capa determinística.

Se reportan `expected_failures` y `probability`. **El nivel de riesgo se decide con el valor
esperado**, no con la probabilidad (evita saturación en flotas de alta tasa).

### Versionado y entrenamiento

- Tabla `predictive_algorithm_versions` con semver y estados `draft` → `published` → `archived`.
- Platform admin entrena (draft) y publica; solo publicadas son seleccionables por empresas.
- Empresa admin: opt-in de recolección + selección de versión.
- Auditoría de train / publish / archive / cambio de settings.

### Catálogo OEM

Modelos OEM globales asociados a `catalog_items.oem_equipment_model_id` de cada tenant
(Mein, Dom-G, …).

### Tecnologías descartadas

- **TensorFlow/Keras**: volumen insuficiente; boosting basta.
- **Qdrant**: `FailureTextNormalizer` cubre el texto libre actual.
- **LangChain**: Phoenix ya orquesta tools vía AI Gateway (ADR-003).

## Consecuencias

- UI `/app/predictive`: flota / por activo; sin pestaña de “catálogos de referencia”.
- Asistente: pide tag/clase/flota y llama `predict_equipment_failures` /
  `get_equipment_health` / `list_failure_modes`.
- Excel ingest (`phoenix:predictive:ingest`) queda para laboratorio y backtest.
- ML opcional (`PHOENIX_PREDICTIVE_ML_ENABLED`); sin dependencia Python en el contenedor app.
