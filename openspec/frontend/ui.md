# UI — Noah (web)

## Layout

- Sidebar persistente con áreas de [information-architecture.md](../design/information-architecture.md).
- Topbar: selector de empresa, usuario, notificaciones (futuro).

## Patrones de pantalla

| Patrón | Uso |
|--------|-----|
| List + detail | Rutinas, activos, facturas |
| Master-detail | Catálogo → ítem |
| Wizard | Onboarding empresa (futuro) |
| Full-screen designer | Formularios y reportes |
| Modal confirmación | Validar, rechazar, emitir factura |

## Estados vacíos

- Copy orientado a acción (“Crea tu primer tipo de rutina”).
- Ilustración ligera opcional.

## Tablas

- Filtros por estado de rutina, sitio, técnico, rango fechas.
- Export CSV fase posterior.

## Formularios dinámicos

- Renderer único `DynamicFormRenderer` consumiendo FormVersion JSON.
- Misma estructura que móvil (paridad de tipos de campo).

## Navegación

Detalle en [navigation.md](navigation.md).
