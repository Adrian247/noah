# 017 — Rediseño UI Material + glass

## Problema

La interfaz actual es funcional pero **austera**: poca jerarquía visual, navegación plana y poca guía para roles (técnico vs supervisor vs facturación).

## Objetivo

- Sistema visual **Material Design 3** (superficies, elevación, tipografía clara) combinado con **glassmorphism** en shell y tarjetas principales.
- Componentes reutilizables: tarjeta glass, botones, badges de estado, cabecera de página, alertas.
- Mejor UX: agrupación del menú por flujo de trabajo, accesos rápidos en dashboard, login acogedor.

## Principios UX

1. **Una acción principal** por pantalla (CTA destacado).
2. **Estado visible** (badges de rutina, avisos rechazo/validación).
3. **Rol**: ocultar o deshabilitar con mensaje claro (facturación vs supervisor).

## Alcance v1

- `AppShell`, `LoginPage`, `DashboardPage`, hub `InvoicesPage` y tokens en `app.css`.
- Resto de páginas heredan shell + pueden adoptar `GlassCard` gradualmente.

## Referencia

Actualizar `openspec/design/design-system.md` con tokens glass/Material.
