# Formulario vehículo — registro (ficha técnica) vs inspección

## Dos formularios, dos usos

| Slug | Uso (`FormUsage`) | Vinculación | Propósito |
|------|-------------------|-------------|-----------|
| `ficha-tecnica-vehiculo-v1` | `equipment` | `equipment_types.vehiculo.default_form_definition_id` | Registrar especificaciones de catálogo (modelo, motor, frenos, dimensiones) desde la ficha L200 |
| `inspeccion-vehiculo-v1` | `routine` | Tipos de rutina / informe (opcional) | Inspección de mantenimiento en ejecución |

## Ficha técnica (registro)

Esquema: `database/seeders/Support/VehicleRegistrationFormSchema.php`.

Secciones alineadas a [`ficha_tecnica_l200_2018.md`](ficha_tecnica_l200_2018.md) §1:

1. Identificación (modelo, año, chasis, variante)
2. Motorización y desempeño
3. Suspensión, frenos y dirección
4. Dimensiones, peso y capacidades

Al alta/edición de un **equipo** de tipo Vehículo, la SPA captura estos campos en `catalog_items.specifications`.

## Inspección (rutina)

Esquema: `database/seeders/Support/NormalizedVehicleFormSchema.php` — ver [`formulario-vehiculo-normalizado.md`](formulario-vehiculo-normalizado.md).

## Insumos por tipo

Cada `supply_type` lleva su formulario de ficha (`FormUsage::Supply`):

| Tipo | Slug formulario |
|------|-----------------|
| `filtros` | `ficha-insumo-filtros-v1` |
| `frenos` | `ficha-insumo-frenos-v1` |
| `suspension` | `ficha-insumo-suspension-v1` |
| `fluidos` | `ficha-insumo-fluidos-v1` |

Esquemas: `database/seeders/Support/NormalizedSupplyFormSchemas.php`.
