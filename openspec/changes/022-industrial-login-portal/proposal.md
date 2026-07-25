# 022 — Portal de acceso industrial (login dashboard)

## Problema

La pantalla de login es un formulario centrado claro; no transmite el contexto industrial ni ofrece ayuda, contacto ni mensaje de servicio antes de autenticarse.

## Objetivo

1. **Login tipo dashboard** oscuro e industrial: panel izquierdo (centrado vertical) con formulario glass y campos estilo Material; panel derecho con imagen de trabajador industrial tenue y **parallax**.
2. Mantener **BacteriumNetwork** como animación de fondo.
3. Secciones de **ayuda**, **contacto** e **información de servicio** en el portal (datos configurables).
4. Módulo **solo administrador**: editar textos, contacto, servicio e imagen del héroe.
5. API pública `GET /portal` para la página de login; `PUT` protegida para administrador.

## Alcance

- Tabla `portal_settings` (fila global por instalación).
- UI `PortalSettingsPage` en Administración.
- Rediseño `LoginPage.vue` + estilos (`field-material`, parallax).

## Fuera de alcance

- Multi-idioma del portal.
- CMS rich-text completo.

## Criterios de aceptación

1. Login responsive: en móvil apila contenido; en desktop dos columnas.
2. Solo administrador edita portal y ve el menú.
3. Cambios visibles en login sin re-deploy (recarga).
