# Entorno demo local (Docker)

## Credenciales

| Email | Contraseña |
|-------|------------|
| `admin@noah.local` | `noah_application` (o `NOAH_DEMO_PASSWORD`) |
| `tecnico@noah.local` | igual |
| `supervisor@noah.local` | igual |
| `facturacion@noah.local` | igual |
| `cliente@noah.local` | igual (portal cliente: facturas y rutinas de equipos vinculados por serie) |

El seed **no** crea una rutina por defecto. Un administrador puede generar una rutina de prueba desde **Rutinas → Generar rutina demo**.

## Ritual obligatorio para asistentes y desarrolladores

Tras **cualquier** cambio que toque `NoahDemoSeeder`, usuarios `@noah.local`, permisos/RBAC, `noah:bootstrap-permissions` o flujo de login demo:

```bash
docker compose exec app php artisan noah:refresh-demo
```

Ese comando ejecuta, en orden: `migrate` → `noah:bootstrap-permissions` → `NoahDemoSeeder` → `noah:ensure-demo --reset-credentials`.

**Al cerrar la tarea**, el asistente debe:

1. Ejecutar `noah:refresh-demo` (o confirmar que no aplica).
2. Indicar al usuario las credenciales de la tabla anterior.

### Variantes

| Situación | Comando |
|-----------|---------|
| Ritual completo | `php artisan noah:refresh-demo` |
| Sin migraciones | `php artisan noah:refresh-demo --skip-migrate` |
| Solo contraseñas | `php artisan noah:ensure-demo --reset-credentials` |

Docker `app` ejecuta `migrate` + `noah:refresh-demo --skip-migrate` al arrancar (`docker-compose.yml`). Si la BD quedó vacía sin reiniciar el contenedor, ejecuta `noah:refresh-demo` a mano.
