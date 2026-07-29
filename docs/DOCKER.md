# Phoenix en Docker

## URLs (con `docker compose up -d`)

| Servicio | URL |
|----------|-----|
| **Phoenix (web + API)** | **http://localhost:8888** |
| Login SPA | http://localhost:8888/login |
| App (dashboard) | http://localhost:8888/app/dashboard |
| API health | http://localhost:8888/api/v1/health |
| Mailpit (correo) | http://localhost:8025 |
| MinIO consola | http://localhost:9001 (usuario `phoenix` / `phoenixsecret`) |

El puerto **8888** lo publica el contenedor **nginx** (`web`). (Si 8888 está ocupado, cambia `8888:80` en `docker-compose.yml`.)

El contenedor **`queue`** ejecuta `php artisan queue:work` (Redis). Los PDF de rutinas validadas se generan en cola; sin `queue` en marcha el reporte quedará en estado `queued`.

## Arranque (primera vez)

Desde la raíz del repo:

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate --force
docker compose exec app php artisan migrate --seed --force
```

El servicio PHP se llama **`app`** (no `php`):

```bash
docker compose exec app php artisan ...
```

### Base de datos ya migrada

- **Solo volver a cargar demo** (idempotente):  
  `docker compose exec app php artisan db:seed --force`
- **Reiniciar todo** (borra datos):  
  `docker compose exec app php artisan migrate:fresh --seed --force`

Si ves `users_email_unique`, los usuarios demo ya existían: usa `db:seed` con el seeder actualizado o `migrate:fresh --seed`.

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
