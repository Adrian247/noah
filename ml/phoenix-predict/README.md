# phoenix-predict

Subproyecto de mantenimiento predictivo de Phoenix. Hace dos cosas independientes:

1. **ETL de bitácoras** (`scripts/extract_logbooks.py`): convierte los libros de Excel de campo a un
   JSON normalizado que consume `php artisan phoenix:predictive:ingest`.
2. **Servicio del modelo** (`app/`): FastAPI que refina la probabilidad de falla que calcula el motor
   determinístico de PHP.

Se despliega aparte de la aplicación: Phoenix funciona completo sin él (ver
[ADR-013](../../openspec/decisions/ADR-013-predictive-maintenance.md)). La guía operativa completa
está en [docs/PREDICTIVE_MAINTENANCE.md](../../docs/PREDICTIVE_MAINTENANCE.md).

## Instalación

```bash
python3 -m venv .venv
.venv/bin/pip install -r requirements.txt
```

## ETL

```bash
.venv/bin/python scripts/extract_logbooks.py --profile plant \
    --site "Planta 4400" -o data/plant-4400.json "Bitácora Planta 4400.xlsm"

.venv/bin/python scripts/extract_logbooks.py --profile underground \
    --site "San Martín" -o data/sanmartin.json "SanMartin - Reporte Diario SEP 09 2020.xlsm"
```

Dos perfiles porque los libros tienen forma distinta: `plant` trae un renglón por día y por equipo
más una hoja de órdenes de trabajo y otra de eventos del PLC; `underground` trae un renglón por turno
con la clave codificada como `<tag><serialExcel><turno>` (p. ej. `JB-10044075T2`).

Lo que el ETL corrige del original, porque afecta al modelo y no solo a la estética:

- **Episodios de falla.** Las horas de reparación se registran turno por turno; una avería de tres
  turnos aparece como tres renglones. Se consolidan en un episodio con el tiempo fuera de servicio
  sumado, si no la tasa de falla sale inflada al triple y el MTBF pierde sentido.
- **Renglones duplicados.** Los libros repiten filas idénticas; se conserva la primera.
- **Fechas fuera de rango.** Hay capturas aisladas con fecha equivocada (dos renglones de septiembre
  2020 tecleados como enero 2020). Se descartan comparando contra la mediana del libro, porque si se
  dejan extienden el periodo de cobertura del equipo ocho meses y envenenan cualquier ventana.

## Entrenamiento

```bash
docker compose exec app php artisan phoenix:predictive:dataset --company=1 --horizon=14 --stride=1
.venv/bin/python scripts/train.py data/training.json
```

El dataset lo arma Laravel porque las características y las reglas de etiquetado son las mismas que
usa el motor determinístico; duplicarlas en Python sería garantizar que se separen.

El modelo es un `HistGradientBoostingClassifier` calibrado (isotónico) cuando hay suficientes
positivos. La partición de validación es **temporal**, no aleatoria: una partición al azar filtraría
el futuro dentro del entrenamiento y daría un AUC inflado. El artefacto queda en
`artifacts/risk-model.joblib`.

Con las bitácoras originales: ROC AUC 0.80 y average precision 0.55 sobre una tasa base de 0.13, en
un holdout temporal de 284 filas.

## Servicio

```bash
.venv/bin/uvicorn app.main:app --host 0.0.0.0 --port 8000
```

- `GET /health` — si el modelo está cargado, su versión y sus métricas de holdout.
- `POST /predict` — recibe el vector de características por activo y la predicción heurística;
  devuelve la probabilidad refinada.

Si el modelo no está entrenado responde 503 a propósito: `PredictionServiceClient` lo interpreta como
"usa el motor determinístico" y el usuario no ve un error.

## Contrato de características

`app/features.py` traduce el mapa que produce `App\Services\Predictive\FeatureBuilder` al vector
numérico. El orden de las columnas quedó grabado al entrenar: **las columnas nuevas se agregan al
final y la lista no se reordena**. Si el número de columnas del contrato deja de coincidir con el del
modelo cargado, el servicio responde 503 en lugar de devolver un número mal alineado.

## Qué no está aquí y por qué

- **TensorFlow/Keras**: con ~1000 observaciones el gradient boosting gana y entrena en segundos, sin
  arrastrar 600 MB al despliegue. Queda comentado en `requirements.txt` para cuando haya series de
  tiempo continuas (vibración, telemetría OEM) que justifiquen una red.
- **Qdrant**: el caso era buscar fallas parecidas por similitud semántica del texto libre, y
  `FailureTextNormalizer` en PHP ya clasifica el 100 % de las descripciones de ambos libros contra la
  taxonomía. Una base vectorial añadiría un servicio con estado sin mejorar la clasificación.

## Archivos generados

`data/`, `artifacts/` y `.venv/` no se versionan. Los fixtures de las pruebas de PHPUnit sí, y se
generan con `scripts/make_test_fixture.py`.
