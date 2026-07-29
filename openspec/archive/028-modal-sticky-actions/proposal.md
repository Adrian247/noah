# 028 — Acciones primarias ancladas en modales

## Objetivo

Mejorar la UX de **diálogos y paneles modales** donde el contenido es largo: el botón **Guardar** (y acciones equivalentes: Crear, Actualizar, Confirmar) debe permanecer **visible en el pie del modal** sin hacer scroll hasta el final del formulario.

## Problema

Patrón actual repetido en varias páginas:

```html
<GlassCard class="max-h-[90vh] ... overflow-y-auto">
  <form> ... muchos campos ... <AppButton>Guardar</AppButton> </form>
</GlassCard>
```

El scroll envuelve todo el card, incluido el pie de acciones. Reportado en **Usuarios de empresa** (matriz de permisos por módulo); mismo riesgo en **Clientes** y otros CRUD con modal.

## Alcance

### 1. Componente reutilizable

Crear `AppModal.vue` (o `ModalShell.vue`) con estructura:

- Overlay (cierre con click fuera / Escape).
- Contenedor `max-h-[90vh] flex flex-col`.
- **Cabecera** fija: título + botón cerrar opcional.
- **Cuerpo** `flex-1 overflow-y-auto`: slot default (formulario).
- **Pie** fijo: slot `footer` con borde superior sutil; fondo glass coherente con `GlassCard`.

Props sugeridas: `open`, `title`, `size` (`sm` | `md` | `lg`), `padding`.

### 2. Migración de pantallas

Auditoría e implementación en modales con formulario largo:

| Área | Archivo | Prioridad |
|------|---------|-----------|
| Usuarios / permisos | `CompanyUsersPage.vue` | Alta |
| Clientes | `ClientsPage.vue` | Alta |
| Otros modales con `max-h-[90vh] overflow-y-auto` | grep en `resources/js` | Media |

Páginas full-page con formulario largo (sin modal) **no** entran en 028 salvo que usen patrón modal inline.

### 3. Comportamiento

- Pie visible en viewports ≥ 320px; en móvil, pie sticky dentro del modal (no del viewport global).
- Botón primario a la derecha; secundario Cancelar a la izquierda (patrón Phoenix existente).
- `disabled` / loading en Guardar sin romper layout del pie.
- Accesibilidad: `role="dialog"`, `aria-labelledby`, foco inicial en primer campo, trap de foco opcional v1.

### 4. Design system

- Añadir sección breve en `openspec/design/design-system.md`: «Modales con acciones fijas».
- Tokens: `modal-footer` border `border-white/10`, padding alineado con `GlassCard`.

## Criterios de aceptación

1. En modal de usuario/permisos, con scroll en la tabla de módulos, **Guardar** y **Cancelar** siempre visibles en el pie.
2. Al menos **Clientes** migrado al mismo componente.
3. Sin regresión visual en tema claro/oscuro.
4. Lista de archivos tocados documentada en `tasks.md`.

## Fuera de alcance

- Reemplazar todos los formularios inline de página completa.
- Drawer lateral (solo modales centrados / bottom sheet existentes si los hay).
