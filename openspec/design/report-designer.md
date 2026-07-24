# Diseñador de reportes — Noah

## Patrón de layout

Tres columnas (desktop); en tablet, paleta colapsable.

```
┌─────────────┬──────────────────────────┬─────────────┐
│  Paleta     │   Vista previa (A4)      │ Propiedades │
│  componentes│   zoom 50-100%           │ del bloque  │
└─────────────┴──────────────────────────┴─────────────┘
```

## Paleta (v1)

| Componente | Descripción |
|------------|-------------|
| Título | Texto fijo o enlazado a campo |
| Párrafo | Campo largo / texto IA |
| Tabla | Columnas desde lista o insumos |
| Imagen | Logo empresa o campo imagen |
| Galería | Campo multi-foto evidencia |
| Firma | Campo firma |
| Código QR | URL o id de rutina |
| Fecha | Formato configurable |
| Separador | Línea / espacio |
| Cabecera / Pie | Repetible; numeración `page X of Y`, inicio en página N |
| Salto de página | Control manual |

## Interacciones

- Arrastrar desde paleta al lienzo.
- Seleccionar bloque → panel derecho: fuente, tamaño, negrita, color, márgenes, alineación.
- Enlace a **campo de formulario** mediante selector (árbol de campos del tipo de rutina).
- Vista previa con **datos de ejemplo** y con **datos de rutina real** (modo supervisor).

## Persistencia

- Mismo modelo JSON que [report-engine.md](../architecture/report-engine.md).
- Versiones: borrador → publicada; rutinas en curso usan versión publicada al crearse.

## Export

- Botón “Generar PDF prueba” (async, notificación al terminar).
- No exportar a Word en v1.

## Wireframe textual (vista previa)

```
┌──────────────────────────────────────┐
│ [Logo]     REPORTE DE MANTENIMIENTO │  ← Cabecera
│ Cliente: {{cliente}}  Fecha: {{fecha}}│
├──────────────────────────────────────┤
│ 1. Descripción                       │  ← Título
│ {{descripcion_ia}}                   │  ← Párrafo (campo)
│ [■■■■] [■■■■] [■■■■]                 │  ← Galería
│ ─────────────────────────────────    │
│ Firma técnico: _________             │
├──────────────────────────────────────┤
│                    Página 2 de 5     │  ← Pie (start_page: 2)
└──────────────────────────────────────┘
```

## Errores a evitar en UX

- Editar HTML crudo como única opción.
- Perder cambios al cambiar zoom (autoguardado cada N segundos).
- Publicar plantilla sin vista previa PDF obligatoria.
