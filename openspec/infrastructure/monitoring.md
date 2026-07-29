# Monitoreo — Phoenix

Complementa [observability.md](../architecture/observability.md).

## Health checks

- `GET /up` — Laravel default.
- `GET /health/deep` — DB, Redis, storage (fase código).

## Dashboards

- Colas Horizon.
- Tasa 5xx API.
- Duración jobs PDF/IA.

## Logs

- Agregación central; retención 30 días.

## On-call (SaaS futuro)

- Pager en caída de health deep o cola > umbral.
