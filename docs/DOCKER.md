# Noah en Docker

## URLs (con `docker compose up -d`)

| Servicio | URL |
|----------|-----|
| **Noah (web + API)** | **http://localhost:8888** |
| Login SPA | http://localhost:8888/login |
| App (dashboard) | http://localhost:8888/app/dashboard |
| API health | http://localhost:8888/api/v1/health |
| Mailpit (correo) | http://localhost:8025 |
| MinIO consola | http://localhost:9001 (usuario `noah` / `noahsecret`) |

El puerto **8888** lo publica el contenedor **nginx** (`web`). (Si 8888 está ocupado, cambia `8888:80` en `docker-compose.yml`.)

## Arranque (primera vez)

Desde la raíz del repo:

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate --force
docker compose exec app php artisan migrate --seed --force
```

En tu máquina (assets de Vue):

```bash
npm install && npm run build
```

Las variables de base de datos y Redis para Docker van en `docker-compose.yml` (sobrescriben un `.env` local con SQLite).

## Comandos útiles

```bash
docker compose ps          # ver contenedores
docker compose logs -f web
docker compose down        # detener
```

## Sin Docker

`php artisan serve` → http://127.0.0.1:8000 (ver [README.md](../README.md)).
