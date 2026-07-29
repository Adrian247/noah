# AI Gateway — Phoenix

La IA es un servicio transversal.

Nunca se invoca un proveedor directamente desde dominio de negocio.

Toda interacción pasa por AI Gateway.

Responsabilidades:

- Seleccionar proveedor
- Administrar y versionar prompts
- Controlar costos y cuotas
- Registrar auditoría
- Administrar herramientas (catálogo futuro)
- Controlar permisos
- Validar respuestas (p. ej. no agregar datos)

Proveedores previstos (adapters): OpenAI, Anthropic, Azure OpenAI, OpenRouter, Ollama.

Caso de uso v1: **corrector técnico** de comentarios de rutina (sin inventar hechos).
