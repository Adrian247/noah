# Entornos — Phoenix

## Variables críticas

| Variable | Descripción |
|----------|-------------|
| `APP_URL` | URL pública web |
| `DB_*` | PostgreSQL |
| `REDIS_*` | Cache y colas |
| `AWS_*` o `MINIO_*` | Object storage |
| `MAIL_*` | SMTP / Mailpit |
| `AI_*` | Keys vía AI Gateway config en BD preferible a env único |

## Local

- `.env.example` documentado al crear proyecto Laravel.
- Mailpit para emails.
- Ollama opcional en host para IA local.

## Staging

- Copia de esquema; datos sintéticos.
- Proveedor IA con límite de gasto.

## Production

- `APP_DEBUG=false`
- Backups automáticos PG ([backups.md](backups.md) — crear si needed)

## Feature flags

- Laravel Pennant por empresa para módulos beta (billing fiscal, designer avanzado).
