# Despliegue — Noah

## Entornos

| Entorno | Uso |
|---------|-----|
| `local` | Docker Compose |
| `staging` | Pre-producción, datos anonimizados |
| `production` | Clientes piloto / SaaS |

Detalle: [environments.md](environments.md).

## Pipeline CI/CD (objetivo)

1. Lint + tests PHP y JS.
2. Build imagen Docker.
3. Migraciones en staging automáticas.
4. Producción: migraciones con ventana de mantenimiento o zero-downtime expand/contract.

## Componentes runtime

- Nginx → PHP-FPM (N instancias).
- Horizon workers separados o mismo imagen con comando distinto.
- PostgreSQL gestionado o contenedor (piloto).
- Redis gestionado.
- S3/MinIO.

## Móvil

- Builds en CI (Codemagic, GitHub Actions + macOS runner para iOS).
- Distribución interna antes de stores.

## Rollback

- Imagen anterior en registry; migraciones reversibles cuando sea posible.
