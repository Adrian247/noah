# Diseñador de formularios — Noah

Complementa [forms-engine.md](../architecture/forms-engine.md) y [domain/forms.md](../domain/forms.md).

## Layout

Misma metáfora que el diseñador de reportes:

- **Izquierda:** tipos de campo (texto, número, foto, firma, …).
- **Centro:** vista del formulario por secciones.
- **Derecha:** validaciones, etiqueta, ayuda, obligatorio, visibilidad por rol.

## Secciones

- Arrastrar campos dentro de secciones colapsables.
- Reordenar secciones y campos.

## Vista previa

- Modo “como técnico” con datos de ejemplo.
- Validaciones en vivo.

## Publicación

- Borrador → **Publicar** crea `FormVersion` y dispara `FormVersionPublished` (sync a móvil).

## Paridad móvil

Solo tipos soportados en [flutter.md](../mobile/flutter.md) aparecen habilitados en paleta (configurable por plataforma en metadatos).
