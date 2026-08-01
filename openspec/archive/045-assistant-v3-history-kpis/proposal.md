# 045 — Assistant v3: historial, tools de módulos, KPIs

## Resumen

Extiende el asistente de plataforma (FAB) con:

1. **Historial** — `conversation_id` + `RemembersConversations` (Laravel AI); en local, UUID + `history` del cliente.
2. **Tools de módulos** — `list_clients`, `list_invoices`, `list_sites`, `get_operational_kpis`.
3. **Dashboard KPIs** — `AssistantDashboardBuilder` genera `presentation` determinística (tipo Taag), no inventada por LLM.
4. **UI** — panel con artefactos KPI/tabla; estilos con tokens `--portal-*` (tema claro legible; se eliminó `login-glass-premium` del panel).

## Tasks

- [x] Registrar tools en `AiToolRegistry`
- [x] Heurística local + formato de respuestas
- [x] API `conversation_id` / `history` / `presentation`
- [x] UI panel + CSS tema claro
- [x] Tests unitarios
