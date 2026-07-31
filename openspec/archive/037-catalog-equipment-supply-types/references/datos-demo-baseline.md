# Datos demo — alineado a ficha L200 2018

Fuente autoritativa de refacciones y especificaciones: [`ficha_tecnica_l200_2018.md`](ficha_tecnica_l200_2018.md).

## Tipos de equipo (semilla)

| code | name |
|------|------|
| `vehiculo` | Vehículo |
| `motor` | Motores |
| `bomba` | Bombas |

## Tipos de insumo (semilla)

Ver detalle: [`catalogo-insumos-normalizado.md`](catalogo-insumos-normalizado.md).

## CatalogItem — Mitsubishi L200 2018

Código **`VEH-L200-2018`**; `specifications` JSON en la ficha §3.

## Asset demo

- **tag:** `L200-2018-DEMO`
- **catalog_item:** VEH-L200-2018
- Activo principal demo (sustituir o convivir con `VEH-SUV-PREM`).

## Insumos (desde ficha §2)

| SKU | Nombre en Phoenix | Tipo | Costo ref. MXN |
|-----|----------------|------|----------------|
| `FRE-P54038-BRM` | Balatas delanteras Brembo P54038 (juego) | frenos | 1268.00 |
| `FIL-1230A153-OEM` | Filtro de aceite Mitsubishi OEM | filtros | 550.00 |
| `SUS-AMORT-GROB-PAR` | Amortiguadores delanteros GROB (par) | suspension | 1698.00 |
| `FIL-AIR-2030515-SAK` | Filtro de aire Sakura | filtros | 341.00 |

Proveedor demo: `PROV-001` (Refacciones del Norte).

## Formulario vehículo

Plantilla **normalizada** `inspeccion-vehiculo-v1` — ver [`formulario-vehiculo-normalizado.md`](formulario-vehiculo-normalizado.md). Demo premium = referencia de patrones, no destino final.

## Ritual

`docker compose exec app php artisan phoenix:refresh-demo` — `docs/DEMO_ENV.md`.
