# Dynamic Report Engine — Noah

Los reportes no son documentos fijos.

Los reportes son árboles de componentes.

Componentes: Título, Texto, Tabla, Imagen, Galería, Firma, Código QR, Gráfico, Cabecera, Pie, Salto de página.

Cada reporte se almacena como JSON.

Pipeline:

```
JSON → HTML → PDF (Chromium / Browsershot)
```

No utilizar plantillas Word como motor principal.

Diseño del editor: [design/report-designer.md](../design/report-designer.md).
