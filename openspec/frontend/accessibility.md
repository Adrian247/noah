# Accesibilidad — Noah (web)

## Objetivo

WCAG 2.1 nivel AA en flujos administrativos principales.

## Checklist de implementación

- Contraste texto/fondo ≥ 4.5:1 (tokens en design-system).
- Focus visible en todos los controles interactivos.
- Labels asociados a inputs; errores anunciados (`aria-invalid`, `aria-describedby`).
- Diseñador de reportes: alternativa de teclado para reordenar componentes (fase 2 del designer).
- Tablas: encabezados `<th scope="col">`.
- No depender solo de color para estados (icono + texto).

## Pruebas

- axe en CI en páginas críticas (Vitest + axe-core).
- Revisión manual de validación de rutina y login.

## Móvil

- Targets táctiles 44px; lectores de pantalla en campos de formulario dinámico con `label` desde esquema.
