# 041 — Pulido integraciones, insights y rendimiento UI

## Objetivo

Cerrar ajustes posteriores a Fase 4: contrato webhook único, rendimiento tema claro/entrada login, animación neural solo en login.

## Alcance

- Eliminar adaptador Discord; JSON Phoenix + HMAC siempre
- Test `DispatchWebhookJobTest`
- Optimizar carga dashboard (API en paralelo, diferir hasta fin overlay entrada)
- Tema claro: frost estático sin backdrop-filter; sin canvas en app
- Login: animación Bacterium completa; pausa canvas durante overlay entrada
- Documentación `integrations.md`
