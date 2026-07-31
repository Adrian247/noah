# Dominio — Catalogs (Phoenix)

## Responsabilidad

Maestros reutilizables: familias de equipo, modelos, documentación de referencia, proveedores (maestro).

## Agregados

### CatalogItem (equipo)

- Código, nombre, fabricante, especificaciones (JSON o campos fijos).
- Documentos adjuntos (manuales) vía Storage.

### Supplier

- Datos de contacto, términos comerciales opcionales.
- Vinculación con insumos en Inventory.

## Distinción Asset vs CatalogItem

| Concepto | CatalogItem | Asset |
|----------|-------------|-------|
| Naturaleza | Plantilla / modelo | Instancia instalada |
| Cantidad | Una definición | Muchas por empresa |

## Tipos de equipo e insumo

- **`equipment_types`**: familias configurables por empresa (p. ej. Vehículo, Motores); formulario por defecto opcional (`FormUsage::Equipment`).
- **`supply_types`**: familias de insumo (p. ej. Filtros, Sublimación); formulario por defecto opcional (`FormUsage::Supply`).
- `catalog_items.equipment_type_id` y `supply_items.supply_type_id` clasifican ítems; ver change archivado `037-catalog-equipment-supply-types`.

## Invariantes

- Código de catálogo único por empresa (o global si catálogo compartido SaaS — decidir en implementación; por defecto por empresa).

## Eventos

- `CatalogItemCreated`, `SupplierCreated`
