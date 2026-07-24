# Seguridad — Noah (resumen)

- Autenticación fuerte; MFA opcional en fase posterior.
- Autorización: **permisos** atómicos + roles; comprobar `company_id` en cada query (multitenancy).
- Aislamiento de datos por empresa en capa de aplicación y índices compuestos.
- AI Gateway: sin enviar datos a modelos sin permiso `ai.invoke` y registro de auditoría.
- Archivos: acceso solo vía URLs firmadas o streaming autenticado.
- Sync móvil: identificación de dispositivo + tokens rotables.

Detalle de implementación en fase de código; alineado con OWASP API Top 10.
