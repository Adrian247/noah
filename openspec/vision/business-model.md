# Modelo de negocio — Phoenix

## Propuesta de valor

Phoenix reduce el costo de **adaptar** operaciones de mantenimiento y documentación técnica: nuevos formatos de servicio, reportes con marca del cliente y flujo validación–factura sin desarrollo a medida.

## Segmentos iniciales

| Segmento | Dolor | Cómo ayuda Phoenix |
|----------|-------|-----------------|
| Empresas de mantenimiento industrial | PDFs y Excel por cliente | Tipos de rutina y plantillas configurables |
| Contratistas multi-sitio | Campo sin señal | Móvil offline + sync (fase 2) |
| Operaciones con supervisión | Evidencia dispersa | Rutina → validación → reporte PDF |
| Áreas con facturación posterior | Re-captura de datos | Borrador desde rutina validada |

## Modelo de ingresos (orientativo)

Fases posteriores al MVP; no bloquean diseño técnico.

| Modelo | Descripción |
|--------|-------------|
| **SaaS por empresa** | Suscripción mensual por tenant + límites de usuarios/activos |
| **Por sitio o activo** | Tier según volumen de activos monitoreados |
| **Módulos** | Facturación fiscal, IA avanzada, almacenamiento extra |
| **Implementación** | Onboarding, migración de catálogos (servicio profesional opcional) |

## Go-to-market (borrador)

1. Piloto con 1–2 empresas (single-tenant operado como multi-tenant en datos).
2. Refinar diseñadores de formulario/reporte según feedback.
3. Abrir registro self-service cuando multitenancy y facturación propia estén maduros.

## Métricas comerciales

Ver [success-metrics.md](success-metrics.md).

## Alcance geográfico

Facturación fiscal depende de país; el núcleo operativo es **agnóstico**. Primer adaptador fiscal por definir con cliente piloto (interfaz en [domain/billing.md](../domain/billing.md)).
