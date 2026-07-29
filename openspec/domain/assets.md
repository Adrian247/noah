# Dominio — Assets (Phoenix)

## Responsabilidad

Instancias de equipo instalado en un sitio. Enlace operativo con rutinas y catálogo.

## Agregados

### Asset

- `company_id`, `site_id`
- Referencia a entrada de catálogo (`catalog_item_id`) o datos libres si no hay catálogo.
- Identificadores: tag interno, número de serie, ubicación dentro del sitio.
- Estado operativo: activo, fuera de servicio, dado de baja.
- Metadatos extensibles (JSON) para atributos no normalizados en v1.

## Relaciones

- Una **Rutina** apunta a un **Asset** (ver [maintenance.md](maintenance.md)).
- Historial de rutinas validadas = historial de mantenimiento del activo (vista de lectura).

## Invariantes

- Asset siempre bajo un sitio de la misma empresa.
- Baja lógica: no asignar nuevas rutinas; historial conservado.

## Eventos

- `AssetRegistered`, `AssetUpdated`, `AssetRetired`
