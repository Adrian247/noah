# 046 — Mantenimiento predictivo para equipo minero

## Contexto

Dos bitácoras reales de operación minera (Unidad San Martín) muestran que la información
necesaria para predecir fallas **ya se captura hoy**, pero en Excel, sin taxonomía y sin
trazabilidad:

| Archivo | Ámbito | Hoja clave | Volumen |
|---------|--------|-----------|---------|
| `Bitácora Planta 4400.xlsm` | Planta de proceso (trituración, molienda, flotación, espesamiento, filtración) | `Data`, `Equipment`, `Events`, `Alarms`, `OT`, `SAP`, `Milestone`, `Inventory` | 1 481 turnos-equipo (2021-04-01 → 2021-06-23), 780 tags, 13 133 eventos de PLC, 1 130 órdenes de trabajo, 12 081 materiales |
| `SanMartin - Reporte Diario SEP 09 2020.xlsm` | Mina interior (jumbos, scooptrams, camiones) | `Datos`, `Equipos`, `Configuracion`, `Barrenos` | 1 357 turnos-equipo (2020-01 → 2020-09), 36 equipos (11 JB, 15 SS, 10 VQ) |

Señales aprovechables detectadas:

- **Horas por estado y turno**: `ScheduledHours`, `HrWork`, `M.Preventive`, `M.Corrective`,
  `OperativeFail`, `StandBy` → disponibilidad, utilización, MTBF, MTTR.
- **Horómetro**: `H Incial` / `H Final` por turno (mina) y `Hours` / `Counter` (planta) →
  vida acumulada y horas desde el último servicio.
- **Consumibles por turno**: `Diesel`, `Aceite`, `Refirgerante` → tasa de consumo por hora,
  cuya desviación es indicador temprano clásico (fuga, desgaste de motor, hidráulico).
- **Texto libre de falla**: `Estatus / Falla` (mina) y `Comments` (planta). Muy sucio y con
  variantes ortográficas del mismo modo de falla (`reparacion de eje`, `reparación de equipo, eje`,
  `reparacio de eje`) → requiere normalización a taxonomía.
- **Eventos y alarmas de PLC** (`Events`, `Alarms`, quebradoras Metso HP-500/HP-400/C-110):
  75 códigos distintos con convención de prefijo (`A` alarma, `W` advertencia, `M` mensaje),
  p. ej. `W651` filtro de aceite hidráulico obstruido (303 ocurrencias), `A403` sobrecarga del
  motor de la trituradora, `A308` nivel de aceite de lubricación muy bajo. Son **precursores**
  medibles de paro correctivo.
- **Cumplimiento de plan preventivo** (`OT`): orden, fecha planeada, `Ejecución` sí/no y motivo
  de incumplimiento → el PM no ejecutado es una de las variables más predictivas de falla.
- **Reemplazos de componente** (`Milestone`): cambio de lainas, embrague, filtros, aceite →
  permite vida remanente (RUL) por componente y no solo por máquina.
- **Avisos de avería SAP** (`SAP`): inicio/fin de avería, duración de parada, grupo de códigos
  de problema y parte del objeto → etiqueta supervisada de falla con fecha y duración.

Phoenix hoy no tiene dónde guardar nada de esto: no existen tablas de telemetría, lectura de
horómetro, evento de máquina, falla ni orden de trabajo externa. El mantenimiento predictivo
figura solo como capacidad futura en `vision/future-capabilities.md`.

## Objetivo

1. Incorporar al dominio de Phoenix una **espina de datos de confiabilidad multipropósito**
   (no específica de minería) capaz de recibir lo que estas bitácoras ya registran y lo que
   registrará la app de campo.
2. Publicar **catálogos de referencia**: taxonomía normalizada de modos de falla, modelos OEM
   (Epiroc, Sandvik, Metso/Outotec) y planes de mantenimiento por intervalo de horas.
3. Construir un **algoritmo predictivo de fallas** con dos capas:
   - **Capa determinística en PHP** (siempre disponible, auditable, sin servicios externos).
   - **Capa de aprendizaje** en un subproyecto Python (`ml/phoenix-predict`) con
     TensorFlow/scikit-learn y Qdrant para similitud semántica de descripciones de falla.
4. Exponer la predicción como **tool del asistente** (el "MCP" de Phoenix es
   `AiToolRegistry`), consultable por equipo, conjunto de equipos, clase, sitio y modo de falla.
5. Usar los datos reales de los dos Excel como **prueba de regresión** del algoritmo.

## Decisiones de arquitectura

### Por qué no LangChain

El bucle de tool-calling, el grounding, la autorización por permiso y la auditoría ya están
resueltos en `app/Services/AI` + `laravel/ai` (ADR-003, cambio 043). Introducir LangChain
duplicaría el orquestador y movería la autorización fuera de Laravel. **Se descarta.**

### Por qué un subproyecto Python, y con qué modelo

La predicción necesita modelos tabulares que PHP no cubre razonablemente. Se aísla en
`ml/phoenix-predict` (FastAPI) para no acoplar el monolito al runtime de Python.

El modelo es **gradient boosting sobre histogramas, calibrado** (`scikit-learn`). Con el volumen
real disponible —del orden de mil observaciones y 85 columnas— gana en calidad y entrena en
segundos. **TensorFlow/Keras se descarta como modelo principal**: arrastraría cientos de MB al
despliegue sin mejorar el resultado a este tamaño de muestra. Queda documentado como extra opcional
para cuando haya series de tiempo continuas (vibración, telemetría OEM) que justifiquen una red
recurrente sobre la secuencia de turnos.

### Por qué se descarta Qdrant

La idea era almacenar embeddings del texto libre de falla para normalizarlo al modo más parecido y
recuperar reparaciones análogas. Medido contra los dos libros, `FailureTextNormalizer` —patrones de
la taxonomía más distancia de edición— clasifica el **100 %** de las descripciones. Introducir una
base vectorial añadiría un servicio con estado sin mejorar la clasificación. Se reconsidera cuando
la recuperación de casos análogos sea un requisito por sí misma.

### Por qué una capa determinística además del modelo

Phoenix ya sigue este patrón en IA (`LocalProvider` cuando no hay LLM). La predicción debe
responder aunque el servicio ML esté caído, en una empresa nueva sin historial suficiente para
entrenar, y debe poder explicar **por qué** subió el riesgo. El motor determinístico calcula
riesgo por función de peligro (hazard) sobre horas desde la última falla, más precursores
ponderados; el modelo ML lo sustituye cuando hay datos y está disponible.

### "Predictivo al 100 %", no solo regresión

La regresión sobre los Excel es la **prueba**, no el producto. El diseño objetivo es
predicción prospectiva: para cada equipo y ventana (7/14/30 días) se estima probabilidad de
falla por modo, con los factores que la explican, y el resultado se persiste en
`failure_predictions` para poder medir después el acierto (precisión, recall, lead time) contra
lo que realmente ocurrió.

## Alcance

### Esquema (migración `2026_08_03_*`)

| Tabla | Propósito |
|-------|-----------|
| `failure_modes` | Taxonomía por empresa: código, sistema, clases de equipo aplicables, severidad, síntomas, causas, señales de monitoreo, horas medias de reparación |
| `equipment_shift_logs` | Bitácora por activo/fecha/turno: horas por estado, horómetro, consumibles, producción, ubicación, texto de falla, disponibilidad/utilización |
| `equipment_events` | Evento o alarma de máquina: código, severidad (`alarm`/`warning`/`message`), conteo, origen |
| `equipment_failures` | Falla con inicio/fin, horas fuera, modo de falla, texto original, tipo de mantenimiento, costo |
| `equipment_work_orders` | Orden de trabajo externa (SAP/CMMS): planeada, ejecutada, motivo de incumplimiento |
| `equipment_component_replacements` | Reemplazo de componente con horómetro, para vida remanente |
| `equipment_measurements` | Medición de condición genérica: métrica, valor, unidad, fecha (vibración, aceite, temperatura) |
| `failure_predictions` | Salida del algoritmo: ventana, probabilidad, riesgo, modo, factores, modelo y versión |
| `oem_equipment_models` | Referencia global: fabricante, familia, modelo, clase, especificaciones |
| `oem_maintenance_plans` / `oem_maintenance_plan_items` | Referencia global: intervalos en horas y tareas por intervalo |

### Software

- ETL `ml/phoenix-predict/scripts/extract_logbooks.py`: convierte los `.xlsm` a un JSON
  normalizado y estable (contrato de ingesta), sin añadir dependencias PHP.
- `php artisan phoenix:predictive:ingest <archivo.json> --company=<id|slug>`: ingesta idempotente.
- `App\Services\Predictive\*`: `FeatureBuilder`, `PredictiveFailureEngine`,
  `FailureTextNormalizer`, `PredictionServiceClient`, `PredictiveMaintenanceService`.
- Tools: `predict_equipment_failures`, `get_equipment_health`, `list_failure_modes`.
- API: `GET /api/v1/predictive/predictions`, `/assets/{asset}/health`, `/failure-modes`,
  `/accuracy` y `POST /evaluate`.
- Comandos: `phoenix:predictive:catalogs`, `:ingest`, `:dataset` (exportación de entrenamiento),
  `:backtest` (evaluación contra el histórico).
- Subproyecto `ml/phoenix-predict`: FastAPI (`/health`, `/predict`) y entrenamiento por script con
  scikit-learn.

### Fuera de alcance

- Integración en vivo con SAP PM o telemetría OEM (Epiroc Fleet+ / My Epiroc — Certiq discontinuado;
  Sandvik My Sandvik / OptiMine). Se deja el contrato de ingesta listo.
- Captura de vibración/análisis de aceite desde la app móvil (la tabla queda, el formulario no).
- Reentrenamiento automático programado. Se entrena por comando.

## Criterios de aceptación

1. `phoenix:predictive:ingest` carga ambos archivos sin duplicar al reejecutarse y crea o
   reutiliza activos por tag.
2. El motor determinístico produce riesgo y factores explicativos para cualquier activo con al
   menos un turno registrado, sin servicio ML disponible.
3. `predict_equipment_failures` responde por activo, lista de activos, clase de equipo, sitio y
   modo de falla, con fuentes citables y respetando permisos y empresa.
4. Existe prueba de regresión con datos reales de ambas bitácoras que fija el orden de riesgo
   esperado y falla si el algoritmo cambia de forma no intencional.
5. Los catálogos de modos de falla y modelos OEM se siembran y son consultables desde el
   asistente.
6. Con el servicio ML arriba, la predicción usa el modelo entrenado; si se cae, degrada al
   motor determinístico sin error para el usuario.

## Resultados medidos

Sobre `Bitácora Planta 4400.xlsm`, que es el único de los dos libros con cobertura suficiente para
validar una ventana de 14 días (84 días útiles, 18 equipos con bitácora):

| Modelo | ROC AUC | Detalle |
|--------|---------|---------|
| Motor determinístico | 0.76 | 368 observaciones, tasa base 13 %; precisión 0.23 y recall 0.44 al umbral de alerta |
| GBDT entrenado | 0.80 | holdout temporal de 284 filas, average precision 0.55 sobre tasa base 0.13, Brier 0.098 |

Tasa de falla observada por nivel de riesgo del motor determinístico: 4.1 % en `low`, 13.3 % en
`medium`, 40 % en `high`.

### Tres correcciones que el backtest hizo visibles

Las tres empezaron como un motor que parecía funcionar y medía ROC AUC 0.53, es decir, azar:

1. **Episodios, no renglones.** Las horas de reparación se registran turno por turno; contar cada
   renglón como una falla triplicaba la tasa y arruinaba el MTBF.
2. **Censura por reparación.** Un equipo que ya está en el taller en la fecha de corte acumula todas
   las señales de falla reciente y su etiqueta es 0 hasta que sale. Incluirlo enseña la relación al
   revés.
3. **Cobertura de datos.** Etiquetar cortes cuya ventana futura cae fuera del periodo que cubre la
   bitácora del equipo produce negativos por construcción. Este era el defecto dominante: al
   corregirlo el AUC pasó de 0.53 a 0.76 sin tocar un solo peso del modelo.

### Límite del reporte de mina

`SanMartin - Reporte Diario SEP 09 2020.xlsm` cubre 13 días. Una ventana de 14 necesita al menos 21
(7 de historia mínima más 14 de futuro observable), así que **no admite validación histórica**. Sirve
para ejercitar ingesta, taxonomía, características y predicción prospectiva. Para validar la flota
subterránea hacen falta tres o cuatro meses continuos del mismo reporte diario.
