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

## Invariantes

- Código de catálogo único por empresa (o global si catálogo compartido SaaS — decidir en implementación; por defecto por empresa).

## Eventos

- `CatalogItemCreated`, `SupplierCreated`
