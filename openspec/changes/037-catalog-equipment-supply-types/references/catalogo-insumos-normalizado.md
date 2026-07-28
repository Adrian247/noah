# Catálogo normalizado — tipos de insumo e ítems

## Objetivo

A partir de la **tabla de refacciones** en [`ficha_tecnica_l200_2018.md`](ficha_tecnica_l200_2018.md), definir un **modelo de catálogo** (tipos + ítems) reutilizable, no una transcripción literal de cada columna del MD.

## Modelo de maestro (normalizado)

### `supply_types`

Taxonomía corta derivada de las refacciones del MD + coherencia con formulario vehículo normalizado:

| code | name | Agrupa en ficha |
|------|------|-----------------|
| `filtros` | Filtros | Aceite, aire |
| `frenos` | Frenos y balatas | Balatas delanteras |
| `suspension` | Suspensión | Amortiguadores |
| `fluidos` | Fluidos y lubricantes | (extensible; sin fila en ficha v1) |

### `supply_items` — campos

| Campo Noah | Origen MD | Normalización |
|------------|-----------|---------------|
| `sku` | — | Código interno estable (ej. `FIL-1230A153-OEM`) |
| `name` | Refacción | Nombre comercial claro |
| `unit` | — | `pza` por defecto; `par` para juegos |
| `standard_cost` | Precio estimado MXN | Punto medio del rango o valor único |
| `supply_type_id` | — | Según tabla tipos |
| `specifications` (JSON) | Marca / modelo recomendado | `{ "marca": "...", "referencia_oem": "...", "notas_mercado": "..." }` |
| `supplier_id` | — | Proveedor demo opcional |

## Ítems semilla (desde ficha)

| SKU | name | type | unit | cost MXN | specifications (resumen) |
|-----|------|------|------|----------|----------------------------|
| `FIL-1230A153-OEM` | Filtro de aceite Mitsubishi OEM | filtros | pza | 550 | marca Mitsubishi, OEM 1230a153 |
| `FIL-AIR-2030515-SAK` | Filtro de aire Sakura 2030515 | filtros | pza | 341 | marca Sakura, ref. 2030515 |
| `FRE-P54038-BRM` | Balatas delanteras Brembo P54038 | frenos | jgo | 1268 | marca Brembo, ref. P54038 |
| `SUS-AMORT-GROB-PAR` | Amortiguadores delanteros GROB | suspension | par | 1698 | marca GROB, par delantero |

## UI CRUD (037)

- Alta/edición de insumo: campos anteriores + selector de **tipo de insumo** obligatorio.
- Opcional: mostrar `referencia_oem` y `marca` desde JSON en formulario de insumo (inputs dedicados que escriben `specifications`).

## Relación con formulario vehículo

El formulario normalizado pregunta por **estado** de sistemas; el catálogo de insumos provee **repuestos** alineados (filtros, frenos, suspensión) para consumo en rutina (fase posterior) o solo consulta en demo.

## Documento anterior

[`catalogo-insumos-desde-ficha.md`](catalogo-insumos-desde-ficha.md) queda sustituido por este modelo; mantener solo como alias si hace falta, pero la fuente de verdad es **este** archivo.
