# Noah

Plataforma configurable para gestión de mantenimiento industrial: catálogos, rutinas, reportes dinámicos, facturación y (en fases posteriores) app móvil offline con sincronización e IA asistida.

| Recurso | Enlace |
|---------|--------|
| Especificación (OpenSpec) | [openspec/README.md](openspec/README.md) |
| Diagramas | [openspec/diagrams/README.md](openspec/diagrams/README.md) |
| Guía para Cursor | [openspec/AGENTS.md](openspec/AGENTS.md) |
| Estado del código | [docs/IMPLEMENTATION.md](docs/IMPLEMENTATION.md) |

## Stack

- **Backend:** Laravel 13, PHP 8.4
- **Frontend:** Vue 3, Vite, Pinia, Vue Router, Tailwind CSS 4
- **Datos:** PostgreSQL, Redis, MinIO (objetos), Mailpit (correo local)

## Desarrollo local (sin Docker)

Requisitos: PHP 8.4+, Composer, Node 20+.

```bash
cp .env.example .env
composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
npm run dev   # terminal 1
php artisan serve   # terminal 2 — http://127.0.0.1:8000
```

SPA: rutas bajo `/app/*`. API: `GET /api/v1/health`.

## Desarrollo con Docker

```bash
cp .env.docker.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
npm install && npm run build
```

- App: http://localhost:8080  
- Mailpit: http://localhost:8025  
- MinIO console: http://localhost:9001  

Ajusta `.env` con `DB_HOST=postgres`, `REDIS_HOST=redis`, etc. (ver `.env.docker.example`).

## Licencia

Por definir.
