# Dominio — Forms (Phoenix)

Vista de dominio del **Dynamic Forms Engine**. Motor técnico: [forms-engine.md](../architecture/forms-engine.md).

## Agregados

### FormDefinition

- Identidad estable (`slug`, nombre, empresa).
- No mutable en contenido; cambios vía versiones.

### FormVersion

- Estado: borrador, publicada, archivada.
- Esquema JSON: secciones → campos → validaciones → reglas UI.
- `published_at`, autor.

## Tipos de campo (v1)

| Tipo | Uso |
|------|-----|
| `text` / `textarea` | Comentarios, descripciones |
| `number` | Horómetro, mediciones |
| `date` / `datetime` | Fechas de servicio |
| `select` / `multiselect` | Listas, catálogos |
| `boolean` | Checklist |
| `photo` | Evidencia (ref storage tras captura) |
| `signature` | Firma |
| `duration` | Tiempo de rutina |
| `section` | Agrupación visual |

## Validaciones

- Requerido, min/max, regex, dependencias entre campos (“si A entonces B obligatorio”).

## Consumo

- Web: renderer en ejecución de rutina.
- Móvil: mismo esquema publicado; cache local en sync.

## Eventos

- `FormVersionPublished`, `FormVersionArchived`
