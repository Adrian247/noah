# Intención de producto — change 037 (aclaración)

## Qué aporta el MD (ficha L200 2018)

El archivo [`ficha_tecnica_l200_2018.md`](ficha_tecnica_l200_2018.md) es **material de referencia**, no un contrato campo a campo:

| Uso | Contenido del MD |
|-----|------------------|
| **Tipo equipo Vehículo — datos a registrar** | Especificaciones técnicas (motor, dimensiones, frenos, suspensión, capacidades) → `CatalogItem.specifications` + plantilla **Mitsubishi L200 2018** y activo demo |
| **Insumos — catálogo inicial** | Tabla de refacciones (nombre, marca/OEM, rango de precio MXN) → `supply_types` + `supply_items` normalizados |

No se exige pegar literalmente cada párrafo del MD en la base de datos; se **extrae** la data necesaria para sembrar y para validar el modelo.

## Formularios (diseño normalizado por tipo)

**No** significa “usar tal cual el formulario demo premium del seeder” ni “replicar el MD como formulario”.

Significa:

1. **Tipo de equipo Vehículo** lleva un **formulario de rutina normalizado** para ese tipo: inspección/mantenimiento de vehículos ligeros/pickup, coherente con la ficha (frenos disco/tambor, filtros, fluidos, kilometraje, etc.) y con la **estructura** que ya demuestra Phoenix (secciones, `options`, fotos opcionales), pero **simplificado y estandarizado** para servir a cualquier vehículo del tipo, no solo al caso premium SUV.

2. **Tipo de insumo** no es un `FormDefinition` de campo en 037, sino un **catálogo normalizado**: campos de maestro (SKU, nombre, tipo, unidad, costo, proveedor, referencias OEM/marca en `specifications` JSON) derivados de las filas del MD.

Si en el futuro un “formulario de alta de insumo” extendido se desea, queda fuera de 037; aquí el CRUD de insumos + tipos basta.

## Resumen en una frase

- **MD → datos concretos** (L200 + refacciones).  
- **Formulario vehículo → plantilla normalizada** inspirada en ficha + demo, aplicable al tipo Vehículo.  
- **Insumos → catálogo normalizado** inspirado en las refacciones del MD.

## Documentos derivados

| Documento | Rol |
|-----------|-----|
| [`formulario-vehiculo-normalizado.md`](formulario-vehiculo-normalizado.md) | Esquema objetivo del formulario tipo Vehículo (secciones/campos) |
| [`catalogo-insumos-desde-ficha.md`](catalogo-insumos-desde-ficha.md) | Tipos + ítems normalizados desde la ficha |
| [`formulario-vehiculo-registro.md`](formulario-vehiculo-registro.md) | ~~solo enlace~~ → ver normalizado; el seeder demo actual es **referencia**, no destino final |
