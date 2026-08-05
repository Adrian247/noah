# Mantenimiento predictivo

Guía operativa del módulo. Arquitectura:
[ADR-013](../openspec/decisions/ADR-013-predictive-maintenance.md).

## Idea central

Tres familias de algoritmo (entrenables solo por el administrador de sistema):

| Familia | Kind | Pregunta |
|---------|------|----------|
| Mantenimiento | `maintenance_hazard_v2` | ¿Qué equipos / artículos requieren servicio de mantenimiento? |
| Manufactura | `manufacturing_demand_v1` | ¿Qué clientes pedirán pronto un servicio de fabricación? |
| Inventario | `inventory_demand_v1` | ¿Qué clientes solicitarán compra de artículos del catálogo? |

**La predicción de mantenimiento** analiza el historial de servicios aplicados a activos
(validados / pendientes de facturación / facturados). Estima qué equipos atender primero y por qué.

Las bitácoras Excel (planta 4400, San Martín) son **corpus de referencia para entrenar y
regresión del motor**, no se cargan en cada predicción ni son la fuente operativa del producto.

## Qué contesta

| Pregunta | Cómo |
|----------|------|
| ¿A qué equipo entro esta semana? | UI `/app/predictive`, tool `predict_equipment_failures` o `GET /api/v1/predictive/predictions` |
| ¿Por qué ese equipo? | Detalle en `/app/predictive`, o `GET /api/v1/predictive/assets/{asset}/health` |
| ¿Qué equipos están en riesgo de una falla hidráulica? | filtro `failure_mode=FUGA_HIDRAULICA` |
| ¿Qué versión del algoritmo usa mi empresa? | Configuración → Mantenimiento predictivo |
| ¿Le está atinando el modelo? | `phoenix:predictive:backtest` y `GET /api/v1/predictive/accuracy` |

## Configuración del cliente (administrador de empresa)

En **Configuración → Predictivo**:

1. **Permitir a Phoenix recopilar información de servicios para entrenamiento** — opt-in
   legal. Phoenix usa el historial de servicios solo dentro de la plataforma para mejorar los
   algoritmos; no se vende ni se expone fuera de la aplicación, salvo obligación legal.
2. **Algoritmos activos** — tres familias:
   - **Mantenimiento** — puedes fijar una versión publicada o dejar la más reciente automática.
   - **Manufactura** e **Inventario** — siempre usan la versión publicada más reciente de
     plataforma (no se fijan por empresa).

## Entrenamiento (administrador de sistema)

En **Plataforma → Algoritmos predictivos** (`/app/platform/predictive`):

1. Elige la **familia** (mantenimiento / manufactura / inventario).
2. Lee la guía en pantalla: documento ≠ regresión.
3. Descarga la **plantilla JSON o CSV** del algoritmo (botones en la UI) o usa los archivos en
   [`resources/predictive/training-templates/`](../resources/predictive/training-templates/).
4. Sustituye códigos de ejemplo por tags/clientes/artículos reales y súbela (opcional).
5. Entrenar genera una versión **draft** con semver, calibración y **reporte de regresión**
   (AUC / filas) vía backtest sobre empresas con opt-in.
6. **Publicar** hace la versión usable (mantenimiento seleccionable por empresas;
   manufactura/inventario aplican calibración publicada automáticamente).
7. **Archivar** la retira; el botón **Regresión** re-corre el backtest sin subir archivo.
8. Auditoría: `predictive.algorithm_*`, `predictive.company_settings_updated`,
   `predictive.training_document_*`.

### Formatos de documentos

- **Mantenimiento:** `asset_tag`, `as_of`, `horizon_days`, `label_failed`
- **Manufactura:** `client_code`, `service_type`, `occurred_at`, `quantity`
- **Inventario:** `client_code`, `catalog_item_code`, `requested_at`, `quantity`

Contrato JSON: `phoenix.predictive.training/v1`. Endpoint de plantilla:
`GET /api/v1/platform/predictive/training-documents/templates/{kind}?format=json|csv`.

## Catálogo OEM ↔ catálogo de equipos

Los modelos OEM (Epiroc, Sandvik, Metso…) son globales. Se asocian a `catalog_items` de cada
tenant (`oem_equipment_model_id`). En demo, Mein y Dom-G reciben el enlace vía
`OemCatalog::linkCompanyCatalog`.

## Cómo se calcula el riesgo

```
E = λ_ajustada · horas_de_operación_esperadas_en_la_ventana
p = 1 − e^(−E)
λ_ajustada = λ_activo · Π factores
```

Fuente primaria de features: servicios aplicados (frecuencia, duración, consumos, backlog,
cumplimiento). Señales de bitácoras de referencia enriquecen si existen, pero **no son
requisito**.

**El nivel de riesgo se decide con `expected_failures`**, no con la probabilidad
(`critical` E ≥ 1.0, `high` ≥ 0.4, `medium` ≥ 0.15).

Cada predicción trae `drivers` con evidencia citables.

## Asistente

Tools: `predict_equipment_failures`, `get_equipment_health`, `list_failure_modes`,
`predict_client_demand`, `predict_inventory_demand`.

El asistente distingue tres intenciones:

- **Equipos (mantenimiento):** pide tag, clase o flota antes de ejecutar. Con tag llama
  `get_equipment_health`; con clase/flota, `predict_equipment_failures`.
- **Demanda de manufactura/instalación:** con «demanda», «manufactura» o «instalación»
  llama `predict_client_demand`.
- **Demanda de inventario:** con «demanda de inventario / artículos / solicitud de compra»
  llama `predict_inventory_demand`.

Las instrucciones de sistema viven en `App\Support\Ai\OperationalAssistantPrompt` (seeder +
agente + fallback del gateway).

## Corpus de referencia (solo entrenamiento / regresión)

El ETL `ml/phoenix-predict/scripts/extract_logbooks.py` convierte Excel → JSON
(`phoenix.predictive.ingest/v1`). Útil para backtest y calibración, no para el flujo diario.

```bash
docker compose exec app php artisan phoenix:predictive:catalogs
docker compose exec app php artisan phoenix:predictive:ingest \
    ml/phoenix-predict/data/sanmartin.json --company=1 --backfill-hour-meter
docker compose exec app php artisan phoenix:predictive:backtest --company=1
```

## Clases de equipo

`SCOOPTRAM`, `CAMION_BAJO_PERFIL`, `JUMBO`, `QUEBRADORA`, `MOLINO`, `CRIBA`,
`CELDA_FLOTACION`, `ESPESADOR`, `FILTRO`, `BANDA_TRANSPORTADORA`, `ALIMENTADOR`, `BOMBA`,
etc. `EquipmentClass` acepta alias (`SS`, `scoop`, `LHD` → `SCOOPTRAM`).

## ML opcional

Subproyecto `ml/phoenix-predict` (FastAPI + HistGradientBoosting). Si no responde,
queda el motor determinístico PHP. Ver ADR-013.
