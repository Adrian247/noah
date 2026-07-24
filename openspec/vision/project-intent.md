# Intención del proyecto — Noah

Documento breve que fija **por qué existe Noah** y **qué problema resuelve**. El detalle técnico y de diseño vive en el resto de `openspec/`.

## Objetivo

Construir **Noah**: plataforma para empresas que prestan o gestionan **mantenimiento industrial**, con:

1. **Catálogos** de equipo, insumos y costos.
2. **Operación** de rutinas/mantenimientos con evidencias.
3. **Reportes dinámicos** (marca, tipografía, cabecera, pie, numeración de páginas configurable).
4. **Facturación** alineada a trabajos validados, sin mezclar lógica fiscal con operación.
5. **Segunda etapa:** app móvil **offline-first** (fotos, tiempos, comentarios, sync) y en web **validación**, **PDF** y **corrección gramatical de comentarios con IA** (sin inventar hechos).

No es un CRUD grande: es una plataforma **configurable por metadatos** (formularios, reportes, flujos), pensada para crecer como producto.

## Principio rector

**Configurar antes que programar** — nuevos tipos de servicio o formatos de cliente se diseñan en administración, no con un deploy por cada variante.

## Alcance explícito

- Proyecto **nuevo**; sin cruce de datos ni código con otros sistemas ([scope.md](scope.md)).
- Nombre del producto: **Noah**.

## Capacidades futuras (IA y visión)

Exploratorias; no bloquean el MVP. Catálogo y fases: [future-capabilities.md](future-capabilities.md).

## Dónde seguir leyendo

| Necesidad | Documento |
|-----------|-----------|
| Modelo mental | [concept.md](concept.md) |
| Fases de entrega | [roadmap.md](roadmap.md) |
| Diagramas | [../diagrams/README.md](../diagrams/README.md) |
| Índice completo | [../README.md](../README.md) |
